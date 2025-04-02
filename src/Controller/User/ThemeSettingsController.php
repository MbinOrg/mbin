<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ThemeSettingsController extends AbstractController
{
    public const MBIN_LANG = 'mbin_lang';
    public const ENTRIES_VIEW = 'entries_view';
    public const ENTRY_COMMENTS_VIEW = 'entry_comments_view';
    public const POST_COMMENTS_VIEW = 'post_comments_view';
    public const KBIN_THEME = 'kbin_theme';
    public const KBIN_FONT_SIZE = 'kbin_font_size';
    public const KBIN_PAGE_WIDTH = 'kbin_page_width';
    public const MBIN_SHOW_USER_DOMAIN = 'mbin_show_users_domain';
    public const MBIN_SHOW_MAGAZINE_DOMAIN = 'mbin_show_magazine_domain';
    public const KBIN_ENTRIES_SHOW_USERS_AVATARS = 'kbin_entries_show_users_avatars';
    public const KBIN_ENTRIES_SHOW_MAGAZINES_ICONS = 'kbin_entries_show_magazines_icons';
    public const KBIN_ENTRIES_SHOW_THUMBNAILS = 'kbin_entries_show_thumbnails';
    public const KBIN_ENTRIES_SHOW_PREVIEW = 'kbin_entries_show_preview';
    public const KBIN_ENTRIES_COMPACT = 'kbin_entries_compact';
    public const KBIN_POSTS_SHOW_PREVIEW = 'kbin_posts_show_preview';
    public const KBIN_POSTS_SHOW_USERS_AVATARS = 'kbin_posts_show_users_avatars';
    public const KBIN_GENERAL_ROUNDED_EDGES = 'kbin_general_rounded_edges';
    public const KBIN_GENERAL_INFINITE_SCROLL = 'kbin_general_infinite_scroll';
    public const KBIN_GENERAL_TOPBAR = 'kbin_general_topbar';
    public const KBIN_GENERAL_FIXED_NAVBAR = 'kbin_general_fixed_navbar';
    public const KBIN_GENERAL_SIDEBAR_POSITION = 'kbin_general_sidebar_position';
    public const KBIN_GENERAL_DYNAMIC_LISTS = 'kbin_general_dynamic_lists';
    public const KBIN_GENERAL_FILTER_LABELS = 'kbin_general_filter_labels';
    public const MBIN_GENERAL_SHOW_RELATED_POSTS = 'mbin_general_show_related_posts';
    public const MBIN_GENERAL_SHOW_RELATED_ENTRIES = 'mbin_general_show_related_entries';
    public const MBIN_GENERAL_SHOW_RELATED_MAGAZINES = 'mbin_general_show_related_magazines';
    public const MBIN_GENERAL_SHOW_ACTIVE_USERS = 'mbin_general_show_active_users';
    public const KBIN_COMMENTS_SHOW_USER_AVATAR = 'kbin_comments_show_user_avatar';
    public const KBIN_COMMENTS_REPLY_POSITION = 'kbin_comments_reply_position';
    public const KBIN_SUBSCRIPTIONS_SHOW = 'kbin_subscriptions_show';
    public const KBIN_SUBSCRIPTIONS_SORT = 'kbin_subscriptions_sort';
    public const KBIN_SUBSCRIPTIONS_IN_SEPARATE_SIDEBAR = 'kbin_subscriptions_in_separate_sidebar';
    public const KBIN_SUBSCRIPTIONS_SIDEBARS_SAME_SIDE = 'kbin_subscriptions_sidebars_same_side';
    public const KBIN_SUBSCRIPTIONS_LARGE_PANEL = 'kbin_subscriptions_large_panel';
    public const KBIN_SUBSCRIPTIONS_SHOW_MAGAZINE_ICON = 'kbin_subscriptions_show_magazine_icon';
    public const MBIN_MODERATION_LOG_SHOW_USER_AVATARS = 'mbin_moderation_log_show_user_avatars';
    public const MBIN_MODERATION_LOG_SHOW_MAGAZINE_ICONS = 'mbin_moderation_log_show_magazine_icons';
    public const MBIN_MODERATION_LOG_SHOW_NEW_ICONS = 'mbin_moderation_log_show_new_icons';
    public const MBIN_LIST_IMAGE_LIGHTBOX = 'mbin_list_image_lightbox';

    public const CLASSIC = 'classic';
    public const CHAT = 'chat';
    public const TREE = 'tree';
    public const COMPACT = 'compact';
    public const LIGHT = 'light';
    public const DARK = 'dark';
    public const KBIN = 'kbin';
    public const SOLARIZED_LIGHT = 'solarized-light';
    public const SOLARIZED_DARK = 'solarized-dark';
    public const TOKYO_NIGHT = 'tokyo-night';
    public const TRUE = 'true';
    public const FALSE = 'false';
    public const LEFT = 'left';
    public const RIGHT = 'right';
    public const TOP = 'top';
    public const BOTTOM = 'bottom';
    public const ALPHABETICALLY = 'alphabetically';
    public const LAST_ACTIVE = 'last_active';
    public const MAX = 'max';
    public const AUTO = 'auto';
    public const FIXED = 'fixed';
    public const ON = 'on';
    public const OFF = 'off';

    public const KEYS = [
        self::ENTRIES_VIEW,
        self::ENTRY_COMMENTS_VIEW,
        self::POST_COMMENTS_VIEW,
        self::KBIN_THEME,
        self::KBIN_FONT_SIZE,
        self::KBIN_PAGE_WIDTH,
        self::KBIN_ENTRIES_SHOW_USERS_AVATARS,
        self::KBIN_ENTRIES_SHOW_MAGAZINES_ICONS,
        self::KBIN_ENTRIES_SHOW_THUMBNAILS,
        self::KBIN_ENTRIES_COMPACT,
        self::KBIN_GENERAL_ROUNDED_EDGES,
        self::KBIN_GENERAL_INFINITE_SCROLL,
        self::KBIN_GENERAL_TOPBAR,
        self::KBIN_GENERAL_FIXED_NAVBAR,
        self::KBIN_GENERAL_SIDEBAR_POSITION,
        self::KBIN_GENERAL_FILTER_LABELS,
        self::KBIN_ENTRIES_SHOW_PREVIEW,
        self::KBIN_POSTS_SHOW_PREVIEW,
        self::KBIN_POSTS_SHOW_USERS_AVATARS,
        self::KBIN_GENERAL_DYNAMIC_LISTS,
        self::MBIN_LANG,
        self::KBIN_COMMENTS_SHOW_USER_AVATAR,
        self::KBIN_COMMENTS_REPLY_POSITION,
        self::KBIN_SUBSCRIPTIONS_SHOW,
        self::KBIN_SUBSCRIPTIONS_SORT,
        self::KBIN_SUBSCRIPTIONS_IN_SEPARATE_SIDEBAR,
        self::KBIN_SUBSCRIPTIONS_SIDEBARS_SAME_SIDE,
        self::KBIN_SUBSCRIPTIONS_LARGE_PANEL,
        self::KBIN_SUBSCRIPTIONS_SHOW_MAGAZINE_ICON,
        self::MBIN_MODERATION_LOG_SHOW_USER_AVATARS,
        self::MBIN_MODERATION_LOG_SHOW_MAGAZINE_ICONS,
        self::MBIN_MODERATION_LOG_SHOW_NEW_ICONS,
        self::MBIN_GENERAL_SHOW_RELATED_POSTS,
        self::MBIN_GENERAL_SHOW_RELATED_ENTRIES,
        self::MBIN_GENERAL_SHOW_RELATED_MAGAZINES,
        self::MBIN_GENERAL_SHOW_ACTIVE_USERS,
        self::MBIN_SHOW_MAGAZINE_DOMAIN,
        self::MBIN_SHOW_USER_DOMAIN,
        self::MBIN_LIST_IMAGE_LIGHTBOX,
    ];

    public const VALUES = [
        self::CLASSIC,
        self::CHAT,
        self::TREE,
        self::COMPACT,
        self::LIGHT,
        self::DARK,
        self::KBIN,
        self::SOLARIZED_LIGHT,
        self::SOLARIZED_DARK,
        self::TOKYO_NIGHT,
        self::TRUE,
        self::FALSE,
        self::LEFT,
        self::RIGHT,
        self::TOP,
        self::BOTTOM,
        self::ALPHABETICALLY,
        self::LAST_ACTIVE,
        self::ON,
        self::OFF,
        '80',
        '90',
        '100',
        '120',
        '150',
        self::MAX,
        self::AUTO,
        self::FIXED,
    ];

    public function __invoke(string $key, string $value, Request $request): Response
    {
        $response = new Response();

        if (\in_array($key, self::KEYS) && \in_array($value, self::VALUES)) {
            $response->headers->setCookie(new Cookie($key, $value, strtotime('+1 year')));
        }

        if (self::MBIN_LANG === $key) {
            $response->headers->setCookie(new Cookie(self::MBIN_LANG, $value, strtotime('+1 year')));
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        return new \Symfony\Component\HttpFoundation\RedirectResponse(
            ($request->headers->get('referer') ?? '/').'#settings',
            302,
            $response->headers->all()
        );
    }

    public static function getShowUserFullName(?Request $request): bool
    {
        if (null === $request) {
            return false;
        }

        return self::TRUE === $request->cookies->get(self::MBIN_SHOW_USER_DOMAIN, 'false');
    }

    public static function getShowMagazineFullName(?Request $request): bool
    {
        if (null === $request) {
            return false;
        }

        return self::TRUE === $request->cookies->get(self::MBIN_SHOW_MAGAZINE_DOMAIN, 'false');
    }
}
