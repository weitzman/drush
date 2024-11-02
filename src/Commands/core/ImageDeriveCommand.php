<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Create an image derivative',
    aliases: ['id', 'image-derive']
)]
final class ImageDeriveCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'image:derive';

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly ModuleHandlerInterface $moduleHandler
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this
            ->addArgument('style_name', InputArgument::REQUIRED, 'An image style machine name.')
            ->addArgument('source', InputArgument::REQUIRED, 'Path to a source image. Optionally prepend stream wrapper scheme. Relative paths calculated from Drupal root.')
            ->addUsage('image:derive thumbnail core/themes/bartik/screenshot.png');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);

        $this->validateModulesEnabled(['image']);
        $this->validateEntityLoad([$input->getArgument('style_name')], 'image_style');
        $this->validateFileExists($input->getArgument('source'));

        $image_style = $this->entityTypeManager->getStorage('image_style')->load($input->getArgument('style_name'));
        $derivative_uri = $image_style->buildUri($input->getArgument('source'));
        if ($image_style->createDerivative($input->getArgument('source'), $derivative_uri)) {
            $io->success(dt('Derivative image created: !uri', ['!uri' => $derivative_uri]));
            return self::SUCCESS;
        }
        return self::FAILURE;
    }

    protected function validateFileExists(string $path): void
    {
        if (!empty($path) && !file_exists($path)) {
            $msg = dt('File not found: !path', ['!path' => $path]);
            throw new InvalidArgumentException($msg);
        }
    }

    protected function validateEntityLoad(array $ids, string $entity_type_id): void
    {
        $loaded = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
        if ($missing = array_diff($ids, array_keys($loaded))) {
            $msg = dt('Unable to load the !type: !str', ['!type' => $entity_type_id, '!str' => implode(', ', $missing)]);
            throw new \InvalidArgumentException($msg);
        }
    }

    protected function validateModulesEnabled(array $modules): void
    {
        $missing = array_filter($modules, fn($module) => !$this->moduleHandler->moduleExists($module));
        if ($missing) {
            $message = dt('The following modules are required: !modules', ['!modules' => implode(', ', $missing)]);
            throw new InvalidArgumentException($message);
        }
    }
}
