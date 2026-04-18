<?php

declare(strict_types=1);

namespace qs9000\thinkuuidv7\command;

use think\console\Command;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;
use qs9000\thinkuuidv7\UuidV7Manager;

/**
 * UUIDv7 Console Command
 */
class UuidV7Command extends Command
{
    protected UuidV7Manager $uuidv7;

    public function __construct(UuidV7Manager $uuidv7)
    {
        parent::__construct();
        $this->uuidv7 = $uuidv7;
    }

    protected function configure(): void
    {
        $this->setName('uuidv7')
            ->setDescription('Generate UUIDv7 identifiers')
            ->addArgument('action', Argument::OPTIONAL, 'Action: generate, batch, benchmark', 'generate')
            ->addOption('count', 'c', Option::VALUE_OPTIONAL, 'Number of UUIDs', 10)
            ->addOption('driver', 'd', Option::VALUE_OPTIONAL, 'Driver: local or redis', null)
            ->addOption('parse', 'p', Option::VALUE_OPTIONAL, 'Parse a UUID');
    }

    protected function execute(Input $input, Output $output): int
    {
        $action = $input->getArgument('action') ?: 'generate';
        $driver = $input->getOption('driver');
        $count = (int) $input->getOption('count');
        $parseUuid = $input->getOption('parse');

        if ($parseUuid) {
            return $this->parse($parseUuid, $output);
        }

        return match ($action) {
            'generate' => $this->generate($driver, $output),
            'batch' => $this->batch($count, $driver, $output),
            'benchmark' => $this->benchmark($count, $driver, $output),
            default => $this->invalidAction($action, $output),
        };
    }

    protected function generate(?string $driver, Output $output): int
    {
        $uuid = $this->uuidv7->generate($driver);

        $output->writeln(json_encode([
            '<info>UUIDv7 Generated:</info>',
            '',
            "  {$uuid}",
            '',
            "  Timestamp: " . $this->uuidv7->timestamp($uuid),
            "  Datetime:  " . $this->uuidv7->datetime($uuid)->format('Y-m-d H:i:s.u'),
        ]));

        return 0;
    }

    protected function batch(int $count, ?string $driver, Output $output): int
    {
        $count = max(1, min($count, 10000));

        $output->writeln("<info>Generating {$count} UUIDv7s...</info>");
        $output->writeln('');

        $uuids = $this->uuidv7->makeBatch($count, $driver);

        foreach (array_slice($uuids, 0, 20) as $uuid) {
            $output->writeln("  {$uuid}");
        }

        if ($count > 20) {
            $output->writeln("  ... and " . ($count - 20) . " more");
        }

        $output->writeln('');
        $output->writeln("<info>Total: {$count} UUIDs generated</info>");

        return 0;
    }

    protected function benchmark(int $count, ?string $driver, Output $output): int
    {
        $count = max(100, min($count, 1000000));

        $output->writeln("<info>Benchmarking {$count} UUIDv7 generations...</info>");
        $output->writeln('');

        $start = hrtime(true);

        for ($i = 0; $i < $count; $i++) {
            $this->uuidv7->generate($driver);
        }

        $end = hrtime(true);
        $duration = ($end - $start) / 1_000_000_000;

        $output->writeln(
            json_encode([
                "  Count:      {$count}",
                "  Duration:   " . number_format($duration, 4) . " seconds",
                "  Per Second: " . number_format($count / $duration, 0) . " IDs/sec",
            ])
        );

        return 0;
    }

    protected function parse(string $uuid, Output $output): int
    {
        if (!$this->uuidv7->validate($uuid)) {
            $output->error("Invalid UUIDv7 format: {$uuid}");
            return 1;
        }

        $uuidObj = $this->uuidv7->parse($uuid);

        $output->writeln(
            json_encode([
                '<info>UUIDv7 Parsed:</info>',
                '',
                "  UUID:       {$uuid}",
                "  Timestamp:  {$uuidObj->getTimestampMs()} ms",
                "  Datetime:   " . $uuidObj->getDatetime()->format('Y-m-d H:i:s.u'),
            ])
        );

        return 0;
    }

    protected function invalidAction(string $action, Output $output): int
    {
        $output->error("Invalid action: {$action}");
        $output->writeln("Valid actions: generate, batch, benchmark");
        return 1;
    }
}
