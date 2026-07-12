<?php
/**
 * Settings Controller
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    /**
     * Option Name
     */
    public const OPTION = 'asraa_growth_engine_options';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Register Settings
     */
    public function register_settings(): void
    {
        register_setting(
            'asraa_growth_engine',
            self::OPTION,
            [
                'sanitize_callback' => [$this, 'sanitize'],
                'default' => [],
            ]
        );
    }

    /**
     * Sanitize Settings
     */
    public function sanitize($input): array
    {
        if (!is_array($input)) {
            $input = [];
        }

        return [

            /*
            |--------------------------------------------------------------------------
            | Company
            |--------------------------------------------------------------------------
            */

            'company_name' => sanitize_text_field($input['company_name'] ?? ''),
            'company_email' => sanitize_email($input['company_email'] ?? ''),
            'company_phone' => sanitize_text_field($input['company_phone'] ?? ''),
            'company_address' => sanitize_textarea_field($input['company_address'] ?? ''),

            /*
            |--------------------------------------------------------------------------
            | AI
            |--------------------------------------------------------------------------
            */

            'ai_provider' => sanitize_text_field(
                $input['ai_provider'] ?? 'openai'
            ),

            'openai_api' => sanitize_text_field(
                $input['openai_api'] ?? ''
            ),

            'gemini_api' => sanitize_text_field(
                $input['gemini_api'] ?? ''
            ),

            'claude_api' => sanitize_text_field(
                $input['claude_api'] ?? ''
            ),

            /*
            |--------------------------------------------------------------------------
            | Google
            |--------------------------------------------------------------------------
            */

            'google_maps_api' => sanitize_text_field(
                $input['google_maps_api'] ?? ''
            ),

            'google_analytics_id' => sanitize_text_field(
                $input['google_analytics_id'] ?? ''
            ),

            'search_console_property' => sanitize_text_field(
                $input['search_console_property'] ?? ''
            ),

            /*
            |--------------------------------------------------------------------------
            | SEO
            |--------------------------------------------------------------------------
            */

            'default_title' => sanitize_text_field(
                $input['default_title'] ?? ''
            ),

            'default_description' => sanitize_textarea_field(
                $input['default_description'] ?? ''
            ),

            'default_keywords' => sanitize_text_field(
                $input['default_keywords'] ?? ''
            ),

            /*
            |--------------------------------------------------------------------------
            | Social
            |--------------------------------------------------------------------------
            */

            'facebook' => esc_url_raw(
                $input['facebook'] ?? ''
            ),

            'instagram' => esc_url_raw(
                $input['instagram'] ?? ''
            ),

            'linkedin' => esc_url_raw(
                $input['linkedin'] ?? ''
            ),

            'youtube' => esc_url_raw(
                $input['youtube'] ?? ''
            ),

            'twitter' => esc_url_raw(
                $input['twitter'] ?? ''
            ),
        ];
    }

    /**
     * Get Option
     */
    public static function get(string $key, mixed $default = ''): mixed
    {
        $options = get_option(self::OPTION, []);

        return $options[$key] ?? $default;
    }

    /**
     * Set Option
     */
    public static function set(string $key, mixed $value): bool
    {
        $options = get_option(self::OPTION, []);

        if (!is_array($options)) {
            $options = [];
        }

        $options[$key] = $value;

        return update_option(
            self::OPTION,
            $options
        );
    }

    /**
     * Get All Options
     */
    public static function all(): array
    {
        $options = get_option(
            self::OPTION,
            []
        );

        return is_array($options)
            ? $options
            : [];
    }

    /**
     * Delete Option
     */
    public static function delete(string $key): bool
    {
        $options = self::all();

        if (!isset($options[$key])) {
            return false;
        }

        unset($options[$key]);

        return update_option(
            self::OPTION,
            $options
        );
    }

    /**
     * Current AI Provider
     */
    public static function aiProvider(): string
    {
        return (string) self::get(
            'ai_provider',
            'openai'
        );
    }

    /**
     * OpenAI Configured?
     */
    public static function hasOpenAI(): bool
    {
        return self::get('openai_api') !== '';
    }

    /**
     * Gemini Configured?
     */
    public static function hasGemini(): bool
    {
        return self::get('gemini_api') !== '';
    }

    /**
     * Claude Configured?
     */
    public static function hasClaude(): bool
    {
        return self::get('claude_api') !== '';
    }
}