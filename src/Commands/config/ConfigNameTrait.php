<?php

namespace Drush\Commands\config;

use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use InvalidArgumentException;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait ConfigNameTrait
{
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasArgument('config_name') && empty($input->getArgument('config_name'))) {
            $io = new DrushStyle($input, $output);
            // Classes using this trait must have a $configFactory property.
            $config_names = $this->configFactory->listAll();
            $choice = $io->suggest('Choose a configuration', array_combine($config_names, $config_names), scroll: 200, required: true);
            $input->setArgument('config_name', $choice);
        }
    }

    // Call this from the execute method of the command that uses this trait.
    protected function validateConfigName(string|array $config_name): void
    {
        $names = StringUtils::csvToArray($config_name);
        foreach ($names as $name) {
            $config = $this->configFactory->get($name);
            if ($config->isNew()) {
                $msg = dt('Config !name does not exist', ['!name' => $name]);
                throw new InvalidArgumentException($msg);
            }
        }
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('config_name')) {
            $suggestions->suggestValues($this->configFactory->listAll());
        }
    }
}
