<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Support\FlexIcon;

class PlaygroundOptions
{
    /**
     * @return array<string, string>
     */
    public static function heroOverflow(): array
    {
        return [
            'overview' => 'Overview',
            'analytics' => 'Analytics',
            'reports' => 'Reports',
            'performance' => 'Performance',
            'engagement' => 'Engagement',
            'audience' => 'Audience',
            'acquisition' => 'Acquisition',
            'retention' => 'Retention',
            'settings' => 'Settings',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function hero(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'analytics' => 'Analytics',
            'reports' => 'Reports',
            'settings' => 'Settings',
        ];
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public static function heroWithIcons(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'icon' => FlexIcon::LayoutCells],
            'analytics' => ['label' => 'Analytics', 'icon' => FlexIcon::ChartBar],
            'reports' => ['label' => 'Reports', 'icon' => FlexIcon::SquareChartBar],
            'settings' => ['label' => 'Settings', 'icon' => FlexIcon::Gear],
        ];
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public static function devices(): array
    {
        return [
            'mobile' => ['label' => 'Mobile', 'icon' => FlexIcon::Smartphone],
            'tablet' => ['label' => 'Tablet', 'icon' => FlexIcon::LayoutColumns],
            'desktop' => ['label' => 'Desktop', 'icon' => FlexIcon::Display],
        ];
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public static function iconExpand(): array
    {
        return [
            'home' => ['label' => 'Home', 'icon' => FlexIcon::House],
            'chat' => ['label' => 'Chat', 'icon' => FlexIcon::Comments],
            'video' => ['label' => 'Video', 'icon' => FlexIcon::Video],
            'print' => ['label' => 'Print', 'icon' => FlexIcon::Printer],
        ];
    }
}
