<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\ImageCommands;
use Drush\Commands\core\ImageFlushCommand;
use Drush\Commands\pm\PmCommands;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Tests image-flush command
 *
 * @group commands
 */
class ImageTest extends UnishApplicationTesterTestCase
{
    public function testImage()
    {
        $this->drush(PmCommands::INSTALL, ['image']);
        $application = $this->getApplication();
        // We aren't testing pm:install so don't use ApplicationTester.
        // This is the recommended approach from https://symfony.com/doc/current/console/calling_commands.html
        // We can't actually do this until pm:install moves from an AnnotatedCommand.
//        $input = new ArrayInput([
//            'command' => PmCommands::INSTALL,
//            'modules' => ['image'],
//        ]);
//        $output = new NullOutput();
//        $returnCode = $application->doRun($input, $output);

        $logo = 'core/misc/menu-expanded.png';
        $styles_dir = $this->webroot() . '/sites/default/files/styles/';
        $thumbnail = $styles_dir . 'thumbnail/public/' . $logo;
        $medium = $styles_dir . 'medium/public/' . $logo;
        if ($this->isDrupalGreaterThanOrEqualTo('10.3.0')) {
            $thumbnail .= '.webp';
            $medium .= '.webp';
        }

        // Remove stray files left over from previous runs
        @unlink($thumbnail);
        $this->assertFileDoesNotExist($thumbnail);

        // Test that "drush image-derive" works.
        $style_name = 'thumbnail';
        $this->drush(ImageCommands::DERIVE, [$style_name, $logo]);
        $this->assertFileExists($thumbnail);

        // Test that "drush image-flush thumbnail" deletes derivatives created by the thumbnail image style.
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([ImageFlushCommand::NAME, 'style_names' => $style_name, '--no-interaction' => true]);
        $output = $applicationTester->getDisplay();
        $this->assertFileDoesNotExist($thumbnail);
        // @todo note stdin testing documented at https://github.com/symfony/symfony/issues/37835

        // Check that "drush image-flush --all" deletes all image styles by creating two different ones and testing its
        // existence afterwards.
        $this->drush(ImageCommands::DERIVE, ['thumbnail', $logo]);
        $this->assertFileExists($thumbnail);
        $this->drush(ImageCommands::DERIVE, ['medium', $logo]);
        $this->assertFileExists($medium);
        $this->drush(ImageFlushCommand::NAME, [], ['all' => null]);
        $this->assertFileDoesNotExist($thumbnail);
        $this->assertFileDoesNotExist($medium);
    }
}
