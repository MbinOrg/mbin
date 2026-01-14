<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Service\Monitor;
use Psr\Log\LoggerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Profiler\NodeVisitor\ProfilerNodeVisitor;
use Twig\Profiler\Profile;

/**
 * Heavily inspired by https://github.com/inspector-apm/inspector-symfony/blob/master/src/Twig/TwigTracer.php.
 */
class MonitorExtension extends AbstractExtension
{
    protected array $runningTemplates = [];

    public function __construct(
        private readonly Monitor $monitor,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * This method is called before the execution of a block, a macro or a
     * template.
     *
     * @param Profile $profile The profiling data
     */
    public function enter(Profile $profile): void
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            return;
        }

        $profile->enter();

        $label = $this->getLabelTitle($profile);
        $this->monitor->startTwigRendering($label, $profile->getType());
        $this->runningTemplates[$label] = $label;
    }

    /**
     * This method is called when the execution of a block, a macro or a
     * template is finished.
     *
     * @param Profile $profile The profiling data
     */
    public function leave(Profile $profile): void
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            return;
        }

        $profile->leave();

        $key = $this->getLabelTitle($profile);
        if (!\array_key_exists($key, $this->runningTemplates)) {
            $this->logger->warning('Trying to leave a node, but we have not entered it, yet {node}', ['node' => $key]);

            return;
        }

        $this->monitor->endTwigRendering($key, $profile->getMemoryUsage(), $profile->getPeakMemoryUsage(), $profile->getName(), $profile->getType(), $profile->getDuration() * 1000);
        unset($this->runningTemplates[$key]);
    }

    public function getNodeVisitors(): array
    {
        return [new ProfilerNodeVisitor(self::class)];
    }

    /**
     * Gets a short description for the segment.
     *
     * @param Profile $profile The profiling data
     */
    private function getLabelTitle(Profile $profile): string
    {
        switch (true) {
            case $profile->isRoot():
                return $profile->getName();

            case $profile->isTemplate():
                return $profile->getTemplate();

            default:
                return \sprintf('%s::%s(%s)', $profile->getTemplate(), $profile->getType(), $profile->getName());
        }
    }
}
