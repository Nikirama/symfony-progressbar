# Custom symfony progressbar

...

![](assets/preview.gif)

## Installation

Use composer to require the small package

    $ composer require nikirama/symfony-progressbar

## Usage


    public function execute(OutputInterface $output, InputInterface $input)
    {
        $progressbar = new ProgressBar(new SymfonyStyle($input, $output), 100);
        
        for($i = 0; $i < 100; $i++) {
            usleep(1000);
            $spinner->advance();
        }
        
        $spinner->finish();
    }
