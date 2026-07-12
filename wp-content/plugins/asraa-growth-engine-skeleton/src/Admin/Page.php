<?php
/**
 * Base Admin Page
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Page
{
    protected string $title = '';

    public function render(): void
    {
        Layout::header($this->title);

        Header::render();

        echo '<div class="asraa-layout">';

        Sidebar::render();

        echo '<div class="asraa-content">';

        $this->content();

        echo '</div>';

        echo '</div>';

        Footer::render();

        Layout::footer();
    }

    abstract protected function content(): void;
}