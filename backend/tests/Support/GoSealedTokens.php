<?php

namespace Tests\Support;

/**
 * Values sealed by monitoreo's own secret.Box (Go 1.26, internal/panel/secret, run through a
 * `go test -overlay` so that repo was not touched) with the master key 32 × 0x07.
 */
final class GoSealedTokens
{
    public const MASTER_KEY = 'BwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwc=';

    /** box.Encrypt("agent-token-0123456789abcdef", Row("servers.token_enc", 7)) */
    public const FOR_SERVER_7 = '744cacdb3063a59b96862da6d8ccf96515f02d75519dfa11ed9b156f19997540fcd1548c7fb297556d73f44b5f151228c5d0e15e3ed80661';

    public const FOR_SERVER_7_PLAIN = 'agent-token-0123456789abcdef';

    /** box.Encrypt("legacy-token-0123456789abcd", ""): sealed before H9, bound to no row. */
    public const WITHOUT_ROW = 'daf9ac84551fa00f8eaf6d6bbfc768ff10a6d294bdad6b08c382ff701abfe8bb051068d8a1817af1c8f9510c70d57b88d84b8822bd2dfd';

    public const WITHOUT_ROW_PLAIN = 'legacy-token-0123456789abcd';
}
