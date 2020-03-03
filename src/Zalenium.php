<?php declare(strict_types=1);

namespace EdmondsCommerce\Zalenium;

use Codeception\Module;
use Codeception\Step;
use Codeception\TestInterface;
use Facebook\WebDriver\Exception\InvalidCookieDomainException;
use Facebook\WebDriver\Exception\WebDriverException;
use Magento\FunctionalTestingFramework\Module\MagentoWebDriver;

class Zalenium extends Module
{
    /**
     * @var bool
     */
    private $success;

    public function _before(TestInterface $test)
    {
        // Update the success flag for the new test
        $this->success = true;

        $name = $test->getMetadata()->getName();
        $this->getWebDriver()->_capabilities(function ($currentCapabilities) use ($name) {
            $currentCapabilities['name'] = $name;

            return $currentCapabilities;
        });

        $this->getWebDriver()->maximizeWindow();
    }

    private function getWebDriver(): MagentoWebDriver
    {
        $moduleClass = '\\' . MagentoWebDriver::class;

        $module = $this->getModule($moduleClass);

        if ($module instanceof MagentoWebDriver) {
            return $module;
        }

        throw new \RuntimeException('Could not get Magento Web Driver');
    }

    public function _afterStep(Step $step)
    {
        $this->reportStep($step->getAction());
        parent::_beforeStep($step); // TODO: Change the autogenerated stub
    }

    private function reportStep(string $step): void
    {
        $this->setCookie('zaleniumMessage', $step);
    }

    private function setCookie(string $name, $value): void
    {
        if (!$this->isSessionRunning()) {
            return;
        }
        try {
            $this->getWebDriver()->setCookie($name, $value);
        } catch (InvalidCookieDomainException|WebDriverException $exception) {
            // Ignore the error - browser not ready to receive cookies yet.
            $this->debug('Could not set cookie: ' . $exception->getMessage());
        }
    }

    private function isSessionRunning(): bool
    {
        return $this->getWebDriver()->webDriver instanceof \Facebook\WebDriver\WebDriver;
    }

    public function _failed(TestInterface $test, $fail)
    {
        $this->success = false;
        $this->reportStep($fail->getMessage());
        $this->setTestStatus(false);
        $this->getWebDriver()->wait(2);
        parent::_failed($test, $fail);
    }

    private function setTestStatus(bool $success): void
    {
        $this->setCookie('zaleniumTestPassed', ($success === true ? 'true' : 'false'));
    }

    public function _after(TestInterface $test)
    {
        if ($this->success) {
            $this->setTestStatus(true);
        }
        parent::_after($test);
    }
}

