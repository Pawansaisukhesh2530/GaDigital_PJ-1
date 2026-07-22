<?php
/**
 * resume_ai_helper.php
 * -----------------------------------------------------------------------------
 * Reusable server-side bridge between the CPVIA PHP application and the FastAPI
 * Resume Intelligence Service. Keeps the FastAPI base URL / timeout in ONE place
 * and forwards a resume upload to POST /api/resume/parse via cURL.
 *
 * This helper is an OPTIONAL enhancement: if the AI service is unreachable it
 * returns a controlled failure and the caller falls back to the manual flow.
 * No FastAPI internals, stack traces, or filesystem paths are ever surfaced to
 * the browser.
 * -----------------------------------------------------------------------------
 */

// --- Configuration (single, overridable source of truth) --------------------
if (!defined('RESUME_AI_BASE_URL')) {
    // Configurable via environment; development default matches the local API.
    $envBase = getenv('RESUME_AI_BASE_URL');
    define('RESUME_AI_BASE_URL', $envBase !== false && $envBase !== '' ? rtrim($envBase, '/') : 'http://127.0.0.1:8000');
}
if (!defined('RESUME_AI_TIMEOUT')) {
    // Local Qwen 2.5 3B inference normally takes ~70–120s and can occasionally
    // run longer. This is the cURL processing budget (how long PHP waits for
    // FastAPI). Configurable via env; default 300s. Applied ONLY to this
    // request path, never to unrelated PHP pages.
    $envTimeout = (int) getenv('RESUME_AI_TIMEOUT');
    define('RESUME_AI_TIMEOUT', $envTimeout > 0 ? $envTimeout : 300);
}
if (!defined('RESUME_AI_CONNECT_TIMEOUT')) {
    // FastAPI is local, so connecting must be fast; a slow connect means the
    // service is down and we fail quickly rather than waiting minutes.
    define('RESUME_AI_CONNECT_TIMEOUT', 5);
}
if (!defined('RESUME_AI_PHP_TIME_LIMIT')) {
    // PHP execution allowance for the Resume AI endpoint = cURL budget + a small
    // safety margin for cleanup/response so cURL times out (controlled JSON)
    // BEFORE PHP would ever raise a fatal max-execution-time error.
    define('RESUME_AI_PHP_TIME_LIMIT', RESUME_AI_TIMEOUT + 20);
}

/**
 * Forward a resume file to the FastAPI parser and return a normalized result.
 *
 * @return array{
 *   ok:bool, status:int, data:?array, meta:?array,
 *   error_code:string, error:string, duration_ms:int
 * }
 */
if (!function_exists('cpvia_resume_ai_parse')) {
    function cpvia_resume_ai_parse(string $tmpPath, string $originalName, string $mime): array
    {
        $result = [
            'ok' => false, 'status' => 0, 'data' => null, 'meta' => null,
            'error_code' => 'AI_SERVICE_UNAVAILABLE', 'error' => 'AI service unavailable.',
            'duration_ms' => 0,
        ];

        if (!function_exists('curl_init')) {
            $result['error_code'] = 'AI_SERVICE_UNAVAILABLE';
            $result['error'] = 'cURL is not available on this server.';
            return $result;
        }

        $url = RESUME_AI_BASE_URL . '/api/resume/parse';
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'resume';
        $curlFile = new CURLFile($tmpPath, $mime !== '' ? $mime : 'application/octet-stream', $safeName);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => $curlFile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => RESUME_AI_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => RESUME_AI_CONNECT_TIMEOUT,
            CURLOPT_FAILONERROR => false,
        ]);

        $start = microtime(true);
        $body = curl_exec($ch);
        $result['duration_ms'] = (int) round((microtime(true) - $start) * 1000);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result['status'] = $status;

        // Transport-level failures (unreachable, DNS, timeout).
        if ($errno !== 0 || $body === false) {
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                $result['error_code'] = 'API_TIMEOUT';
                $result['error'] = 'The AI service took too long to respond.';
            } else {
                $result['error_code'] = 'AI_SERVICE_UNAVAILABLE';
                $result['error'] = 'The AI service could not be reached.';
            }
            return $result;
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            $result['error_code'] = 'INVALID_RESPONSE';
            $result['error'] = 'The AI service returned an invalid response.';
            return $result;
        }

        if ($status === 200 && !empty($decoded['success']) && isset($decoded['data'])) {
            $result['ok'] = true;
            $result['data'] = $decoded['data'];
            $result['meta'] = $decoded['meta'] ?? null;
            $result['error_code'] = '';
            $result['error'] = '';
            return $result;
        }

        // Controlled API error — pass through the code, keep the message safe.
        $code = 'PROCESSING_FAILED';
        if (isset($decoded['error']['code']) && is_string($decoded['error']['code'])) {
            $code = preg_replace('/[^A-Z_]/', '', strtoupper($decoded['error']['code'])) ?: 'PROCESSING_FAILED';
        }
        $result['error_code'] = $code;
        $result['error'] = 'Resume processing failed.';
        return $result;
    }
}
