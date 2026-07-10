<?php
/**
 * bot.php — Alt Trader Telegram Bot API helper
 * ─────────────────────────────────────────────
 * This runs SERVER-SIDE on Hostinger.
 * Your server is NOT in Pakistan, so it can reach
 * Telegram's Bot API freely even when users can't.
 *
 * What it does:
 *   1. Calls Telegram Bot API to generate a fresh invite link
 *   2. Returns it as JSON to the landing page
 *   3. Landing page redirects user to that link
 *
 * Setup:
 *   1. Create a bot via @BotFather on Telegram → get BOT_TOKEN
 *   2. Add your bot as admin to your channel
 *   3. Give it "Invite Users via Link" permission
 *   4. Get your channel's numeric ID (use @userinfobot)
 *   5. Fill in BOT_TOKEN and CHANNEL_ID below
 *   6. Upload this file to public_html/ on Hostinger
 */

// ─── CONFIGURE THESE TWO VALUES ──────────────────
define('BOT_TOKEN',  '8184032943:AAFyM0kN_vLhu3pOXE_N6oD7Ip-LUDbBIfc');   // from @BotFather
define('CHANNEL_ID', '-1001618111841'); // e.g. -1001234567890
// ─────────────────────────────────────────────────

// Fallback invite link (always works even if Bot API fails)
define('FALLBACK_LINK', 'https://t.me/+B34wSIxKaQEzMWFk');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'link' => FALLBACK_LINK]);
    exit;
}

// Basic rate limiting — prevent abuse (max 60 req/min per IP)
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$file = sys_get_temp_dir() . '/tg_rate_' . md5($ip);
$now  = time();
$hits = [];

if (file_exists($file)) {
    $hits = array_filter(json_decode(file_get_contents($file), true) ?? [], function($t) use ($now) {
        return ($now - $t) < 60;
    });
}
if (count($hits) >= 60) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests', 'link' => FALLBACK_LINK]);
    exit;
}
$hits[] = $now;
file_put_contents($file, json_encode(array_values($hits)));

// Call Telegram Bot API — createChatInviteLink
// expire_date = now + 10 minutes (prevents link hoarding)
// member_limit = 1 (one-time use — better for tracking)
$payload = json_encode([
    'chat_id'      => CHANNEL_ID,
    'expire_date'  => $now + 600,   // 10 min window
    'member_limit' => 1,            // one person per link
    'name'         => 'TikTok-' . substr(md5($ip . $now), 0, 6),
]);

$apiUrl = 'https://api.telegram.org/bot' . BOT_TOKEN . '/createChatInviteLink';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    // Bot API failed → return fallback
    echo json_encode(['link' => FALLBACK_LINK, 'source' => 'fallback']);
    exit;
}

$data = json_decode($response, true);

if (!empty($data['ok']) && !empty($data['result']['invite_link'])) {
    $link = $data['result']['invite_link'];
    echo json_encode(['link' => $link, 'source' => 'bot_api']);
} else {
    echo json_encode(['link' => FALLBACK_LINK, 'source' => 'fallback']);
}
