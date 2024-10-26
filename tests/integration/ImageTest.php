<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\ImageCommands;
use Drush\Commands\core\ImageFlushCommand;
use Drush\Commands\pm\PmCommands;
use Symfony\Component\Console\Tester\ApplicationTester;
use Unish\Controllers\RuntimeController;

/**
 * Tests image-flush command
 *
 * @group commands
 */
class ImageTest extends UnishIntegrationTestCase
{
    public function testImage()
    {
        $this->drush(PmCommands::INSTALL, ['image']);
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

        // Test that "drush image-derive" works.
        $style_name = 'thumbnail';
        $this->drush(ImageCommands::DERIVE, [$style_name, $logo]);
        $this->assertFileExists($thumbnail);

        // Test that "drush image-flush thumbnail" deletes derivatives created by the thumbnail image style.
        // @todo Perhaps create a $this->getApplication() method with the line below.
        // @todo Simplify RuntimeController once all commands are using a Tester? Its singleton is still useful.
        $application = RuntimeController::instance()->application($this->webroot(), [$this->getDrush()]);
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([ImageFlushCommand::NAME, 'style_names' => $style_name]);
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
