<?php

declare(strict_types=1);

namespace Nikirama\ProgressBar;

use Symfony\Component\Console\Helper\ProgressBar as BaseProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;

class ProgressBar
{
    protected const TITLE_MESSAGE = 'title';
    protected const PROGRESS_MESSAGE = 'progress';
    protected const LOGS_MESSAGE = 'logs';
    protected const SPACE_MESSAGE = 'space';

    protected string $barCharacter = '<fg=green>▓</>';
    protected string $emptyBarCharacter = '<fg=red>░</>';
    protected string $progressCharacter = '<fg=green>▓</>';

    protected float $lastWriteTime = 0;
    protected float $minSecondsBetweenRedraws;

    protected string $title = '';
    protected array $logs = [];

    protected array $warnings = [];
    protected array $errors = [];

    protected OutputInterface $output;
    protected Terminal $terminal;
    protected BaseProgressBar $progressBar;

    public function __construct(
        SymfonyStyle $output,
        int $max = 0,
        float $minSecondsBetweenRedraws = 0.1,
        int $lineLength = 60
    ) {
        $this->output = $output;
        $this->terminal = new Terminal;
        $this->progressBar = new BaseProgressBar($output, $max, $minSecondsBetweenRedraws);

        $this->minSecondsBetweenRedraws = $minSecondsBetweenRedraws;

        $this->progressBar->setBarCharacter($this->barCharacter);
        $this->progressBar->setEmptyBarCharacter($this->emptyBarCharacter);
        $this->progressBar->setProgressCharacter($this->progressCharacter);

        $progressLength = strlen((string)$max) * 2 + 1;
        $lineLength = min($lineLength, $this->terminal->getWidth());

        $this->progressBar->setBarWidth($lineLength - 8 - $progressLength);
        $this->progressBar->setFormat("<fg=white;bg=cyan>%" . self::TITLE_MESSAGE . ":-{$lineLength}s%</>\n%" . self::PROGRESS_MESSAGE . ":-{$progressLength}s% [%bar%] %percent:3s%%\n%estimated:-" . (floor($lineLength / 2) - 1) . "s% %memory:" . ceil($lineLength / 2) . "s%\n%" . self::LOGS_MESSAGE . ":-{$lineLength}s%%" . self::SPACE_MESSAGE . "%");

        $this->setProgressMessage();
        $this->display();

        $this->progressBar->start();
    }

    protected function setProgressMessage(int $plus = 0)
    {
        $this->progressBar->setMessage(
            ($this->progressBar->getProgress() + $plus) . "/{$this->progressBar->getMaxSteps()}",
            self::PROGRESS_MESSAGE
        );
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
        $this->display();
    }

    public function writeInfo(string $message)
    {
        $this->logs[] = "<fg=green>$message</>";
        $this->display();
    }

    public function writeWarning(string $message)
    {
        $this->warnings[] = $message;
        $this->logs[] = "<fg=yellow>$message</>";
        $this->display();
    }

    public function writeError(string $message)
    {
        $this->errors[] = $message;
        $this->logs[] = "<fg=red>$message</>";
        $this->display();
    }

    protected function display()
    {
        if ($this->lastWriteTime && microtime(true) - $this->lastWriteTime < $this->minSecondsBetweenRedraws) {
            return;
        }

        $allowedHeight = $this->terminal->getHeight() - 4;
        if ($allowedHeight < count($this->logs)) {
            $this->logs = array_slice($this->logs, count($this->logs) - $allowedHeight);
        }

        $this->progressBar->setMessage($this->title, self::TITLE_MESSAGE);
        $this->progressBar->setMessage(implode("\n", $this->logs), self::LOGS_MESSAGE);
        $this->progressBar->setMessage(
            str_repeat("\n", $this->terminal->getHeight() - 3 - count($this->logs) - (count($this->logs) ? 0 : 1)),
            self::SPACE_MESSAGE
        );

        $this->lastWriteTime = microtime(true);
        $this->progressBar->display();
    }

    protected function clear()
    {
        $this->logs = [];
        $this->display();
    }

    public function advance(int $step = 1)
    {
        $this->setProgressMessage($step);
        $this->clear();

        $this->progressBar->advance($step);
    }

    public function finish(bool $askForTable = false)
    {
        $this->progressBar->finish();

        if ($askForTable && ($this->warnings || $this->errors)) {
            $this->askForTable();
        }
    }

    protected function askForTable()
    {
        $answer = $this->output->ask(
            'Show warnings and errors?',
            'Yes',
            static fn ($answer) => strtolower($answer) === 'yes',
        );

        if ($answer) {
            $this->renderTable();
        }
    }

    protected function renderTable()
    {
        $headers = [];
        if ($this->warnings) {
            $headers[] = 'Warnings';
        }
        if ($this->errors) {
            $headers[] = 'Errors';
        }

        $rows = [];
        for ($i = 0; $i < max(count($this->warnings), count($this->errors)); $i++) {
            $rows[] = [$this->warnings[$i] ?? '', $this->errors[$i] ?? ''];
        }

        $this->output->table($headers, $rows);
    }
}