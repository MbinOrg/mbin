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
    public const KBIN_LANG = 'kbin_lang';
    public const ENTRIES_VIEW = 'entries_view';
    public const ENTRY_COMMENTS_VIEW = 'entry_comments_view';
    public const POST_COMMENTS_VIEW = 'post_comments_view';
    public const KBIN_THEME = 'kbin_theme';
    public const KBIN_FONT_SIZE = 'kbin_font_size';
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
    public const KBIN_FEDERATION_ENABLED = 'kbin_federation_enabled';
    public const KBIN_COMMENTS_SHOW_USER_AVATAR = 'kbin_comments_show_user_avatar';
    public const KBIN_COMMENTS_REPLY_POSITION = 'kbin_comments_reply_position';
    public const KBIN_GENERAL_SHOW_SUBSCRIPTIONS = 'kbin_general_show_subscriptions';
    public const KBIN_GENERAL_SHOW_SUBSCRIPTIONS_SORT = 'kbin_general_show_subscriptions_sort';
    public const KBIN_GENERAL_SHOW_SUBSCRIPTIONS_IN_SEPARATE = 'kbin_general_show_subscriptions_seperate';
    public const KBIN_GENERAL_SIDEBARS_SAME_SIDE = 'kbin_general_sidebars_same_side';

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

    public const KEYS = [
        self::ENTRIES_VIEW,
        self::ENTRY_COMMENTS_VIEW,
        self::POST_COMMENTS_VIEW,
        self::KBIN_THEME,
        self::KBIN_FONT_SIZE,
        self::KBIN_ENTRIES_SHOW_USERS_AVATARS,
        self::KBIN_ENTRIES_SHOW_MAGAZINES_ICONS,
        self::KBIN_ENTRIES_SHOW_THUMBNAILS,
        self::KBIN_ENTRIES_COMPACT,
        self::KBIN_GENERAL_ROUNDED_EDGES,
        self::KBIN_GENERAL_INFINITE_SCROLL,
        self::KBIN_GENERAL_TOPBAR,
        self::KBIN_GENERAL_FIXED_NAVBAR,
        self::KBIN_GENERAL_SIDEBAR_POSITION,
        self::KBIN_ENTRIES_SHOW_PREVIEW,
        self::KBIN_POSTS_SHOW_PREVIEW,
        self::KBIN_POSTS_SHOW_USERS_AVATARS,
        self::KBIN_GENERAL_DYNAMIC_LISTS,
        self::KBIN_FEDERATION_ENABLED,
        self::KBIN_LANG,
        self::KBIN_COMMENTS_SHOW_USER_AVATAR,
        self::KBIN_COMMENTS_REPLY_POSITION,
        self::KBIN_GENERAL_SHOW_SUBSCRIPTIONS,
        self::KBIN_GENERAL_SHOW_SUBSCRIPTIONS_SORT,
        self::KBIN_GENERAL_SHOW_SUBSCRIPTIONS_IN_SEPARATE,
        self::KBIN_GENERAL_SIDEBARS_SAME_SIDE,
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
        '80',
        '90',
        '100',
        '120',
        '150',
    ];

    public function __invoke(string $key, string $value, Request $request): Response
    {
        $response = new Response();

        if (in_array($key, self::KEYS) && in_array($value, self::VALUES)) {
            $response->headers->setCookie(new Cookie($key, $value, strtotime('+1 year')));
        }

        //        if (self::KBIN_THEME === $key && self::KBIN === $value) {
        //            $response->headers->setCookie(new Cookie(self::KBIN_GENERAL_ROUNDED_EDGES, 'true', strtotime('+1 year')));
        //        }

        if (self::KBIN_LANG === $key) {
            $response->headers->setCookie(new Cookie(self::KBIN_LANG, $value, strtotime('+1 year')));
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
}
