<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Router;
use Tests\TestCase;

class LoginPageHeadersTest extends TestCase
{
    /**
     * Proxies buffer upstream response headers (nginx default 4–8KB).
     * Production 502'd when the preload Link header reached ~61KB, so any
     * remaining Link header must stay far below the buffer size.
     */
    private const int SAFE_LINK_HEADER_BYTES = 4096;

    public function test_login_page_responds_ok_without_oversized_link_header(): void
    {
        $response = $this->get('/login');

        $response->assertOk();

        $link = $response->headers->get('Link');
        $linkBytes = $link === null ? 0 : strlen($link);

        $this->assertLessThanOrEqual(
            self::SAFE_LINK_HEADER_BYTES,
            $linkBytes,
            "Link header is {$linkBytes} bytes; oversized preload headers 502 behind the proxy.",
        );
    }

    /**
     * The local header stays small even with the middleware enabled, so the
     * size assertion alone cannot catch a re-registration. Guard the web
     * group structurally instead.
     */
    public function test_preload_link_middleware_is_not_registered_on_web_group(): void
    {
        $webGroup = app(Router::class)->getMiddlewareGroups()['web'] ?? [];

        $this->assertNotContains(
            AddLinkHeadersForPreloadedAssets::class,
            $webGroup,
            'AddLinkHeadersForPreloadedAssets emits a Link header that grows with the build manifest and 502s production.',
        );
    }

    public function test_login_page_still_sets_session_cookies(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertNotEmpty(
            $response->headers->getCookies(),
            'Removing the preload Link middleware must not affect Set-Cookie.',
        );
    }
}
