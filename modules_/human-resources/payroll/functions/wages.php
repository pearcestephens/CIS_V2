<?php
/**
 * HR Payroll Portal Functions (Single-File, Production-Grade)
 *
 * Responsibilities:
 *  - Per-page CSRF management (session-silo'd)
 *  - OTP/MFA with throttle & auto-lock
 *  - Xero Payroll (NZ): list payslips, detail, apply adjustments (draft-only)
 *  - Safe uploads to /private
 *  - OCR/Document extraction via OpenAI Vision (uses OpenAIHelper key mgmt), optional Google Vision via openAIVision class
 *  - Deputy hours cross-check
 *  - Admin queue: list/apply
 *  - PDF export via Dompdf
 *
 * Dependencies assumed available from app bootstrap:
 *  - DB connection via global $pdo  (PDO)
 *  - Composer autoload already loaded by /app.php
 *  - Session already started by /app.php
 *  - Helpers: require_fn(), template(), etc.
 *  - Optionally: functions getUserInformation(), getDeputyTimeSheetsSpecificDay()
 *
 * Notes:
 *  - No `use` statements by design (avoid alias collisions).
 *  - Fully-qualified class names everywhere.
 */

declare(strict_types=1);

if (!defined('PP_CSRF_KEY')) {
    define('PP_CSRF_KEY', 'pp_csrf_token'); // session key for this feature's CSRF token
}
if (!defined('PP_OTP_KEYS')) {
    define('PP_OTP_KEYS', 'pp_otp');        // session key prefix for OTP state
}

final class PayrollPortal
{
    // ====== Public entry points ======

    /** Handle all AJAX actions for this feature. Emits JSON + exit. */
    public static function handleAjax(): void
    {
        self::headersNoCache();
        $action = (string)($_POST['ajax'] ?? '');

        // OTP endpoints do not require CSRF or MFA
        if ($action === 'otp_request') {
            $o = self::otpRequest();
            self::json(['ok'=>$o['sent'], 'channel'=>$o['channel'], 'to'=>$o['to_mask'], 'csrf'=>self::csrfToken()]);
        }
        if ($action === 'otp_verify') {
            $ok = self::otpVerify((string)($_POST['code'] ?? ''));
            self::json(['ok'=>$ok]);
        }
        if ($action === 'ping') {
            if (self::mfaRequired()) self::json(['ok'=>false,'locked'=>true], 401);
            $_SESSION[self::k('last_activity')] = time();
            self::json(['ok'=>true]);
        }

        // Everything else: require MFA + CSRF
        if (self::mfaRequired()) self::json(['ok'=>false,'error'=>'Locked'], 401);
        if (!self::csrfOk((string)($_POST['csrf'] ?? ''))) self::json(['ok'=>false,'error'=>'Bad CSRF'], 400);

        $user = self::currentUser();

        try {
            switch ($action) {
                case 'list_payslips':
                    self::json(['ok'=>true, 'payslips'=>self::listPayslips($user)]);
                    break;

                case 'payslip_detail':
                    $payslipId = (string)($_POST['payslipId'] ?? '');
                    if ($payslipId === '') self::json(['ok'=>false,'error'=>'Missing payslipId'], 400);
                    self::json(['ok'=>true] + self::payslipDetail($payslipId));
                    break;

                case 'upload_evidence':
                    $res = self::uploadEvidence($_FILES['file'] ?? null);
                    self::json(['ok'=>true] + $res);
                    break;

                case 'submit_issue':
                    $payload = [
                        'payslipId' => (string)($_POST['payslipId'] ?? ''),
                        'payRunId'  => (string)($_POST['payRunId'] ?? ''),
                        'lineType'  => (string)($_POST['lineType'] ?? ''),
                        'lineId'    => (string)($_POST['lineId'] ?? ''),
                        'change'    => (string)($_POST['changeType'] ?? ''),
                        'hours'     => (float)($_POST['hours'] ?? 0),
                        'amount'    => (float)($_POST['amount'] ?? 0),
                        'note'      => trim((string)($_POST['note'] ?? '')),
                        'consent'   => filter_var($_POST['consent'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'periodEnd' => (string)($_POST['periodEnd'] ?? ''),
                        'eHash'     => (string)($_POST['evidenceHash'] ?? ''),
                        'ocrJson'   => (string)($_POST['ocrJson'] ?? ''),
                    ];
                    [$ok, $anoms] = self::submitIssue($user, $payload);
                    self::json(['ok'=>$ok,'anomalies'=>$anoms]);
                    break;

                case 'list_issues':
                    if (!self::isAdmin()) self::json(['ok'=>false,'error'=>'Not admin'], 403);
                    self::json(['ok'=>true, 'rows'=>self::listIssues()]);
                    break;

                case 'apply_now':
                    if (!self::isAdmin()) self::json(['ok'=>false,'error'=>'Not admin'], 403);
                    $issueId = (int)($_POST['issueId'] ?? 0);
                    $applied = self::applyNow($issueId);
                    self::json(['ok'=>$applied,'applied'=>$applied]);
                    break;

                case 'export_pdf':
                    $html = (string)($_POST['html'] ?? '');
                    if ($html === '') self::json(['ok'=>false,'error'=>'Missing HTML'], 400);
                    $file = self::exportPdf($html);
                    self::json(['ok'=>true,'file'=>$file]);
                    break;

                default:
                    self::json(['ok'=>false,'error'=>'Unknown action'], 400);
            }
        } catch (\Throwable $e) {
            error_log('[PayrollPortal] AJAX error: '.$e->getMessage());
            self::json(['ok'=>false,'error'=>'Server error'], 500);
        }
    }

    /** Return the current user array (id, names, deputy_id, xero_employee_id, email, phone). */
    public static function currentUser(): array
    {
        // Prefer app-level auth helper if provided
        if (function_exists('auth_user')) {
            $u = auth_user();
            $id = (int)($u['id'] ?? $u->id ?? 0);
            if ($id <= 0) { http_response_code(401); exit('Not signed in'); }
            return [
                'id'               => $id,
                'first_name'       => (string)($u['first_name'] ?? $u->first_name ?? 'Staff'),
                'last_name'        => (string)($u['last_name'] ?? $u->last_name ?? ''),
                'deputy_id'        => (int)($u['deputy_id'] ?? $u->deputy_id ?? 0),
                'xero_employee_id' => (string)($u['xero_employee_id'] ?? $u->xero_employee_id ?? ''),
                'email'            => (string)($u['email'] ?? $u->email ?? ''),
                'phone'            => (string)($u['phone'] ?? $u->phone ?? ''),
            ];
        }

        // Fallback: session + DB lookup
        $staffId = (int)($_SESSION['staff_id'] ?? 0);
        if ($staffId <= 0) { http_response_code(401); exit('Not signed in'); }

        if (function_exists('getUserInformation')) {
            $u = getUserInformation($staffId);
            return [
                'id'               => $staffId,
                'first_name'       => (string)($u['first_name'] ?? $u->first_name ?? 'Staff'),
                'last_name'        => (string)($u['last_name'] ?? $u->last_name ?? ''),
                'deputy_id'        => (int)($u['deputy_id'] ?? $u->deputy_id ?? 0),
                'xero_employee_id' => (string)($u['xero_employee_id'] ?? $u->xero_employee_id ?? ''),
                'email'            => (string)($u['email'] ?? $u->email ?? ''),
                'phone'            => (string)($u['phone'] ?? $u->phone ?? ''),
            ];
        }

        // Last fallback: minimal row
        $row = self::dbOne("SELECT first_name,last_name,email,phone,deputy_id,xero_employee_id FROM staff WHERE id=? LIMIT 1", [$staffId]) ?? [];
        return [
            'id'               => $staffId,
            'first_name'       => (string)($row['first_name'] ?? 'Staff'),
            'last_name'        => (string)($row['last_name'] ?? ''),
            'deputy_id'        => (int)($row['deputy_id'] ?? 0),
            'xero_employee_id' => (string)($row['xero_employee_id'] ?? ''),
            'email'            => (string)($row['email'] ?? ''),
            'phone'            => (string)($row['phone'] ?? ''),
        ];
    }

    /** Per-page CSRF token */
    public static function csrfToken(): string
    {
        if (empty($_SESSION[PP_CSRF_KEY])) {
            $_SESSION[PP_CSRF_KEY] = bin2hex(random_bytes(20));
        }
        return (string)$_SESSION[PP_CSRF_KEY];
    }

    /** True if page is MFA-locked */
    public static function mfaRequired(): bool
    {
        if (!empty($_SESSION[self::k('lock')])) return true;
        if (empty($_SESSION[self::k('ok')]))   return true;
        $last = (int)($_SESSION[self::k('last_activity')] ?? 0);
        $ttl  = 15 * 60; // 15 minutes inactivity window
        if ((time() - $last) > $ttl) {
            $_SESSION[self::k('lock')] = true;
            $_SESSION[self::k('ok')]   = false;
            return true;
        }
        return false;
    }

    /** Force lock on fresh GET (call this in your page before rendering UI) */
    public static function lockOnEntry(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_POST['ajax'])) {
            $_SESSION[self::k('ok')]   = false;
            $_SESSION[self::k('lock')] = true;
        }
    }

    /** True if current user is admin (customize to your auth model). */
    public static function isAdmin(): bool
    {
        return !empty($_SESSION['is_admin'])
            || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','owner','hr'], true));
    }

    // ====== OTP / MFA ======

    public static function otpRequest(): array
    {
        if (!self::otpCanRequest()) {
            return ['sent'=>false,'channel'=>'blocked','to_mask'=>''];
        }
        $u = self::currentUser();
        $_SESSION[self::k('ok')]           = false;
        $_SESSION[self::k('lock')]         = true;
        $_SESSION[self::k('last_activity')]= 0;

        $code = self::otpCode();
        $_SESSION[self::k('hash')]   = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION[self::k('started')] = time();

        $msg = "Your verification code is $code (expires in 5 min).";
        $sent = false; $channel = 'sms';
        if (!empty($u['phone'])) $sent = self::sendSms($u['phone'], $msg);
        if (!$sent && !empty($u['email'])) { $sent = self::sendEmail($u['email'], 'Your verification code', nl2br($msg)); $channel='email'; }
        self::otpLogRequest();

        $to = $sent ? ($channel==='sms' ? $u['phone'] : $u['email']) : ($u['phone'] ?: $u['email']);
        return ['sent'=>$sent,'channel'=>$channel,'to_mask'=>self::mask($to)];
    }

    public static function otpVerify(string $code): bool
    {
        if (empty($_SESSION[self::k('hash')])) return false;
        // Strict 5 min code expiry
        if ((time() - (int)($_SESSION[self::k('started')] ?? 0)) > 5*60) return false;
        if (!password_verify(preg_replace('/\D+/', '', $code), (string)$_SESSION[self::k('hash')])) return false;

        $_SESSION[self::k('ok')]           = true;
        $_SESSION[self::k('lock')]         = false;
        $_SESSION[self::k('last_activity')]= time();
        return true;
    }

    // ====== Business actions ======

    /** List recent payslips for the current Xero employee (~last 6–7 months). */
    public static function listPayslips(array $user): array
    {
        [$api, $tenant] = self::xeroApi();
        $rows     = [];
        $monthsBack = date('Y-m-d', strtotime('-7 months'));

        $page  = 1;
        $runs  = $api->getPayRuns($tenant, $page, null);
        foreach ((array)($runs->getPayRuns() ?? []) as $pr) {
            $end = $pr->getPayPeriodEndDate()?->format('Y-m-d') ?? '';
            if ($end && $end < $monthsBack) continue;

            $payRunId = $pr->getPayRunId();
            $ps = $api->getPaySlips($tenant, $payRunId, 1);
            foreach ((array)($ps->getPaySlips() ?? []) as $sl) {
                if ((string)$sl->getEmployeeId() !== (string)$user['xero_employee_id']) continue;
                $rows[] = [
                    'payslipId'   => $sl->getPaySlipId(),
                    'payRunId'    => $payRunId,
                    'periodStart' => $pr->getPayPeriodStartDate()?->format('Y-m-d'),
                    'periodEnd'   => $pr->getPayPeriodEndDate()?->format('Y-m-d'),
                    'status'      => $pr->getStatus(),
                    'netPay'      => $sl->getNetPay(),
                ];
            }
        }
        usort($rows, fn($a,$b)=>strcmp((string)$b['periodEnd'], (string)$a['periodEnd']));
        return array_slice($rows, 0, 30);
    }

    /** Get payslip detail lines (earnings + reimbursements + totals). */
    public static function payslipDetail(string $payslipId): array
    {
        [$api, $tenant] = self::xeroApi();
        $resp = $api->getPaySlip($tenant, $payslipId);
        $s    = $resp->getPaySlip();

        $earn = [];
        foreach ((array)($s->getEarningsLines() ?? []) as $el) {
            $earn[] = [
                'id'            => $el->getEarningsLineId(),
                'name'          => $el->getName(),
                'ratePerUnit'   => $el->getRatePerUnit(),
                'numberOfUnits' => $el->getNumberOfUnits(),
                'amount'        => $el->getAmount(),
            ];
        }
        $reim = [];
        foreach ((array)($s->getReimbursementLines() ?? []) as $rl) {
            $reim[] = [
                'id'                  => $rl->getReimbursementLineId(),
                'reimbursementTypeId' => $rl->getReimbursementTypeId(),
                'name'                => $rl->getName(),
                'amount'              => $rl->getAmount(),
            ];
        }

        return [
            'earnings'        => $earn,
            'reimbursements'  => $reim,
            'gross'           => $s->getGrossEarnings(),
            'tax'             => $s->getTax(),
            'net'             => $s->getNetPay(),
        ];
    }

    /** Upload evidence + OCR (OpenAI Vision via OpenAIHelper key; optional Google Vision fallback). */
    public static function uploadEvidence(?array $file): array
    {
        $saved = self::safeUpload($file ?? []);
        $ocr   = self::extractDocumentFields($saved['path'], $saved['mime']);
        return ['file'=>$saved, 'ocr'=>$ocr];
    }

    /** Submit a discrepancy (stores in DB + anomaly detection). */
    public static function submitIssue(array $user, array $in): array
    {
        $payslipId = $in['payslipId'];
        $payRunId  = $in['payRunId'];
        $lineType  = $in['lineType'];
        $lineId    = $in['lineId'];
        $change    = $in['change'];
        $hours     = (float)$in['hours'];
        $amount    = (float)$in['amount'];
        $note      = $in['note'];
        $consent   = (bool)$in['consent'];
        $period    = $in['periodEnd'] ?: date('Y-m-d');
        $eHash     = $in['eHash'];
        $ocrJson   = $in['ocrJson'];

        if (!$payslipId || !$payRunId || !$lineId || !$consent) {
            return [false, []];
        }

        $anoms = [];

        // Deputy hours cross-check on the provided date (simple heuristic)
        $rosterHrs = self::deputyHoursForDay((int)($user['deputy_id'] ?? 0), $period);
        if (in_array($change, ['underpaid_time','overpaid_time'], true)) {
            $claimHrs = abs($hours);
            if ($claimHrs > 0 && $rosterHrs > 0 && $claimHrs > ($rosterHrs + 0.25)) {
                $anoms[] = ['type'=>'over_hours_vs_roster','claimed'=>$claimHrs,'rostered'=>$rosterHrs];
            }
        }

        // Duplicate evidence hash?
        if ($eHash) {
            $exists = self::dbOne("SELECT id FROM payroll_wage_issues WHERE JSON_EXTRACT(evidence_json,'$.hash')=? AND status IN ('pending','approved') LIMIT 1", [$eHash]);
            if ($exists) $anoms[] = ['type'=>'duplicate_receipt','other_issue_id'=>$exists['id']];
        }

        // Duplicate OCR (same date+total)?
        if (is_string($ocrJson) && strlen($ocrJson) > 2) {
            $ocr = @json_decode($ocrJson, true);
            if (is_array($ocr) && !empty($ocr['total']) && !empty($ocr['date_iso'])) {
                $d = (string)$ocr['date_iso']; $t = (string)$ocr['total'];
                $dupe = self::dbOne("SELECT id FROM payroll_wage_issues WHERE JSON_EXTRACT(ocr_json,'$.date_iso')=? AND JSON_EXTRACT(ocr_json,'$.total')=? AND status IN ('pending','approved') LIMIT 1", [$d,$t]);
                if ($dupe) $anoms[] = ['type'=>'duplicate_ocr_amount_date','other_issue_id'=>$dupe['id']];
            }
        }

        $ok = self::dbExec(
            "INSERT INTO payroll_wage_issues
             (staff_id,xero_employee_id,payslip_id,payrun_id,line_type,line_id,change_type,hours,amount,note,evidence_json,ocr_json,anomaly_json,status,created_at)
             VALUES (:staff_id,:xero_employee_id,:payslip_id,:payrun_id,:line_type,:line_id,:change_type,:hours,:amount,:note,:evidence_json,:ocr_json,:anomaly_json,'pending',:created_at)",
            [
                ':staff_id'         => $user['id'],
                ':xero_employee_id' => $user['xero_employee_id'],
                ':payslip_id'       => $payslipId,
                ':payrun_id'        => $payRunId,
                ':line_type'        => $lineType,
                ':line_id'          => $lineId,
                ':change_type'      => $change,
                ':hours'            => $hours,
                ':amount'           => $amount,
                ':note'             => $note,
                ':evidence_json'    => json_encode(['hash'=>$eHash]),
                ':ocr_json'         => $ocrJson ?: null,
                ':anomaly_json'     => json_encode($anoms),
                ':created_at'       => date('Y-m-d H:i:s'),
            ]
        );

        return [$ok, $anoms];
    }

    /** Admin: list pending issues */
    public static function listIssues(): array
    {
        return self::dbAll("SELECT * FROM payroll_wage_issues WHERE status='pending' ORDER BY created_at DESC LIMIT 200");
    }

    /** Admin: apply now to Xero (pay run must be DRAFT). */
    public static function applyNow(int $issueId): bool
    {
        $issue = self::dbOne("SELECT * FROM payroll_wage_issues WHERE id=?", [$issueId]);
        if (!$issue) return false;

        [$api, $tenant] = self::xeroApi();

        // Ensure draft
        $pr = $api->getPayRun($tenant, (string)$issue['payrun_id']);
        $status = strtoupper((string)$pr->getPayRun()->getStatus());
        if ($status !== 'DRAFT') {
            self::json(['ok'=>false,'error'=>'Pay run is posted. Revert to draft in Xero first.'], 409);
        }

        $payslipId = (string)$issue['payslip_id'];
        $slip      = $api->getPaySlip($tenant, $payslipId)->getPaySlip();

        $ps = new \XeroAPI\XeroPHP\Models\PayrollNz\PaySlip();

        if ($issue['line_type'] === 'earnings' && in_array($issue['change_type'], ['underpaid_time','overpaid_time'], true)) {
            $delta = new \XeroAPI\XeroPHP\Models\PayrollNz\EarningsLine();
            $delta->setName('Portal Adjustment');
            $delta->setEarningsRateId($slip->getEarningsLines()[0]->getEarningsRateId() ?? null);
            $delta->setNumberOfUnits((float)$issue['hours'] ?: 0.0);
            $ps->setEarningsLines([$delta]);
        } elseif ($issue['line_type'] === 'reimbursement' || $issue['change_type'] === 'reimbursement') {
            $r = new \XeroAPI\XeroPHP\Models\PayrollNz\ReimbursementLine();
            $r->setName('Portal Reimbursement');
            $r->setAmount((float)$issue['amount']);
            $r->setReimbursementTypeId($slip->getReimbursementLines()[0]->getReimbursementTypeId() ?? null);
            $ps->setReimbursementLines([$r]);
        } else {
            self::json(['ok'=>false,'error'=>'Unsupported change'], 400);
        }

        $idemp = bin2hex(random_bytes(16)); // idempotency
        $api->updatePaySlipLineItems($tenant, $payslipId, $ps, $idemp);

        self::dbExec(
            "UPDATE payroll_wage_issues SET status='approved', approved_at=NOW(), approved_by=? WHERE id=?",
            [$_SESSION['staff_id'] ?? 0, $issueId]
        );

        return true;
    }

    /** Export minimal HTML snapshot to PDF and save under /private/pdfs. Returns download path (relative). */
    public static function exportPdf(string $html): string
    {
        if (!class_exists('\Dompdf\Dompdf')) throw new \RuntimeException('Dompdf not installed');
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled'=>false]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();

        $root = self::rootPath();
        $dir  = rtrim($root, '/').'/private/pdfs';
        if (!is_dir($dir)) mkdir($dir, 0770, true);
        $name = 'payslip-'.date('Ymd-His').'.pdf';
        file_put_contents($dir.'/'.$name, $pdf);

        return "private/pdfs/$name";
    }

    // ====== Helpers ======

    /** Returns [\XeroAPI\XeroPHP\Api\PayrollNzApi $api, string $tenantId] */
    private static function xeroApi(): array
    {
        static $api = null, $tenantId = null;
        if ($api && $tenantId) return [$api, $tenantId];

        [$config, $tenantId] = self::xeroContext(); // throws if fails
        $api = new \XeroAPI\XeroPHP\Api\PayrollNzApi(new \GuzzleHttp\Client(), $config);
        return [$api, $tenantId];
        // Note: Fully-qualified to avoid collisions with other `use` imports in pages
    }

    /**
     * Bootstrap Xero Configuration + TenantId.
     * Strategy:
     *  1) Use globals if app already bootstrapped ($config, $xeroTenantId)
     *  2) Else try DB-backed refresh using league/oauth2-client + .env
     */
    private static function xeroContext(): array
    {
        // 1) Already available from app?
        if (!empty($GLOBALS['config']) && !empty($GLOBALS['xeroTenantId'])) {
            return [$GLOBALS['config'], (string)$GLOBALS['xeroTenantId']];
        }

        // 2) Self-bootstrap from DB table xero_auth2 (JSON field)
        $clientId     = getenv('CLIENT_ID')     ?: ($_ENV['CLIENT_ID']     ?? '');
        $clientSecret = getenv('CLIENT_SECRET') ?: ($_ENV['CLIENT_SECRET'] ?? '');
        $redirectUri  = getenv('REDIRECT_URI')  ?: ($_ENV['REDIRECT_URI']  ?? '');

        if (!$clientId || !$clientSecret) {
            throw new \RuntimeException('Missing Xero OAuth credentials');
        }

        $row = self::dbOne("SELECT data_json FROM xero_auth2 ORDER BY id DESC LIMIT 1");
        $data = $row ? json_decode((string)$row['data_json'], true) : [];
        $tenantId    = (string)($data['tenant_id']     ?? '');
        $accessToken = (string)($data['token']         ?? '');
        $refresh     = (string)($data['refresh_token'] ?? '');
        $expires     = (int)   ($data['expires']       ?? 0);

        if (!$tenantId) throw new \RuntimeException('Missing Xero tenant');

        $needRefresh = (time() >= $expires - 60) || empty($accessToken);

        if ($needRefresh) {
            // Refresh via League OAuth2 GenericProvider
            $provider = new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId'                => $clientId,
                'clientSecret'            => $clientSecret,
                'redirectUri'             => $redirectUri ?: 'https://example.com/xero-callback',
                'urlAuthorize'            => 'https://login.xero.com/identity/connect/authorize',
                'urlAccessToken'          => 'https://identity.xero.com/connect/token',
                'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation',
            ]);

            $new = $provider->getAccessToken('refresh_token', ['refresh_token' => $refresh]);
            $accessToken = $new->getToken();
            $refresh     = $new->getRefreshToken();
            $expires     = $new->getExpires();

            // Persist new tokens
            $save = [
                'token'         => $accessToken,
                'tenant_id'     => $tenantId,
                'refresh_token' => $refresh,
                'id_token'      => $new->getValues()['id_token'] ?? null,
                'expires'       => $expires,
            ];
            self::dbExec("INSERT INTO xero_auth2 (data_json) VALUES (?)", [json_encode($save)]);
        }

        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($accessToken);
        // Optional: set user-agent or debug callbacks on $config->getHttpClient();

        return [$config, $tenantId];
    }

    /** Upload file safely under /private/uploads, returns [path,mime,hash,name] */
    private static function safeUpload(array $f): array
    {
        if (!isset($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) throw new \RuntimeException('No file uploaded');
        $size = (int)($f['size'] ?? 0);
        if ($size <= 0 || $size > (8 * 1024 * 1024)) throw new \RuntimeException('File too large');

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($f['tmp_name']);
        $allowed = ['image/jpeg','image/png','application/pdf'];
        if (!in_array($mime, $allowed, true)) throw new \RuntimeException('Unsupported file type');

        $ext  = ($mime==='application/pdf') ? 'pdf' : (($mime==='image/png') ? 'png' : 'jpg');
        $hash = sha1_file($f['tmp_name']);

        $root = self::rootPath();
        $dir  = rtrim($root, '/').'/private/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException('Failed to create directory');
        }
        $name = $hash.'.'.$ext;
        $dest = $dir.'/'.$name;

        if (!file_exists($dest)) {
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                throw new \RuntimeException('Failed to move upload');
            }
        }
        return ['path'=>$dest,'mime'=>$mime,'hash'=>$hash,'name'=>$name,'url'=>null];
    }

    /**
     * Extract structured fields (supplier, date_iso, totals) from image/PDF.
     * Prefers OpenAI Vision via OpenAIHelper key; falls back to mock if disabled.
     * Optionally: if class openAIVision exists, we log or incorporate detection info.
     */
    private static function extractDocumentFields(string $filePath, string $mime): array
    {
        // Primary: OpenAI Vision (image_url base64) using OpenAIHelper key mgmt
        $key = (class_exists('OpenAIHelper') ? \OpenAIHelper::getKey() : (getenv('OPENAI_API_KEY') ?: ''));
        if ($key) {
            try {
                $imgB64 = '';
                $usedMime = 'image/png';
                if ($mime === 'application/pdf') {
                    if (class_exists('Imagick')) {
                        $im = new \Imagick();
                        $im->setResolution(200,200);
                        $im->readImage($filePath."[0]");
                        $im->setImageFormat('png');
                        $imgB64 = base64_encode($im->getImageBlob());
                        $im->clear();
                        $usedMime = 'image/png';
                    } else {
                        // Send PDF directly if Imagick not present (may be less reliable)
                        $imgB64 = base64_encode((string)file_get_contents($filePath));
                        $usedMime = 'application/pdf';
                    }
                } else {
                    $imgB64 = base64_encode((string)file_get_contents($filePath));
                    $usedMime = $mime;
                }

                $prompt = "Extract fields as strict JSON only for NZ receipts/ invoices/ payslips:
{supplier, date_iso, subtotal, gst, total, currency, invoice_number, notes, items:[{desc,qty,unit,amount}]}
Use ISO date, NZD default if absent. Return ONLY JSON.";

                $payload = [
                    "model" => "gpt-4o",
                    "messages" => [[
                        "role" => "user",
                        "content" => [
                            ["type"=>"text","text"=>$prompt],
                            ["type"=>"image_url","image_url"=>["url"=>"data:$usedMime;base64,$imgB64"]],
                        ],
                    ]],
                    "temperature" => 0.0,
                    "max_tokens"  => 800,
                ];

                $ch = curl_init("https://api.openai.com/v1/chat/completions");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        "Authorization: Bearer $key",
                        "Content-Type: application/json",
                    ],
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);
                $res  = curl_exec($ch);
                if ($res === false) throw new \RuntimeException('OpenAI call failed: '.curl_error($ch));
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($code < 200 || $code >= 300) throw new \RuntimeException("OpenAI error $code: $res");

                $j   = json_decode($res, true);
                $txt = $j['choices'][0]['message']['content'] ?? '{}';
                $json = json_decode($txt, true);
                if (is_array($json)) {
                    return $json;
                }
                return ['raw'=>$txt];
            } catch (\Throwable $e) {
                error_log('[PayrollPortal] OpenAI Vision extraction error: '.$e->getMessage());
            }
        }

        // Optional: Log Google Vision analysis via your openAIVision helper (best effort)
        if (class_exists('openAIVision')) {
            try {
                $imgData = 'data:'.($mime==='application/pdf' ? 'application/pdf' : $mime).';base64,'.base64_encode((string)file_get_contents($filePath));
                $question = (object)['desc' => 'document quality and visible text'];
                $res = \openAIVision::analyzeQualityImage($imgData, $question);
                if (is_array($res)) {
                    \openAIVision::logAnalysis('payroll_portal_vision', $res);
                }
            } catch (\Throwable $e) {
                // non-fatal
            }
        }

        // Fallback minimal
        return [
            'supplier' => null, 'date_iso'=> null, 'subtotal'=> null, 'gst'=> null, 'total'=> null,
            'currency' => 'NZD', 'invoice_number'=> null, 'notes'=> 'OCR unavailable',
            'items' => []
        ];
    }

    /** Get Deputy hours for a specific day via your helper if available. */
    private static function deputyHoursForDay(int $deputyId, string $ymd): float
    {
        if (!function_exists('getDeputyTimeSheetsSpecificDay') || !$deputyId) return 0.0;
        try {
            $rows = getDeputyTimeSheetsSpecificDay($deputyId, $ymd, $ymd);
            $hrs = 0.0;
            foreach ((array)$rows as $r) {
                $s = (int)($r['StartTime'] ?? 0);
                $e = (int)($r['EndTime']   ?? 0);
                if ($e > $s) $hrs += ($e - $s)/3600;
            }
            return (float)$hrs;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    // ====== Low-level utilities ======

    private static function headersNoCache(): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    private static function json($data, int $code=200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function csrfOk(string $token): bool
    {
        return $token !== '' && hash_equals(self::csrfToken(), $token);
    }

    private static function otpCode(): string
    {
        $n=''; for($i=0;$i<6;$i++) $n .= random_int(0,9); return $n;
    }

    private static function otpCanRequest(): bool
    {
        $win = 10*60; // 10 minutes
        $key = self::k('otp_req_log');
        $now = time();
        $_SESSION[$key] = array_values(array_filter((array)($_SESSION[$key] ?? []), fn($t)=>$now - $t < $win));
        return count($_SESSION[$key]) < 3;
    }

    private static function otpLogRequest(): void
    {
        $_SESSION[self::k('otp_req_log')][] = time();
    }

    private static function sendSms(string $to, string $text): bool
    {
        $sid   = getenv('TWILIO_SID')   ?: '';
        $token = getenv('TWILIO_TOKEN') ?: '';
        $from  = getenv('TWILIO_FROM')  ?: '';
        if (!$sid || !$token || !$from || !$to) return false;
        try {
            $tw = new \Twilio\Rest\Client($sid, $token);
            $tw->messages->create($to, ['from'=>$from, 'body'=>$text]);
            return true;
        } catch (\Throwable $e) {
            error_log('[PayrollPortal] SMS send failed: '.$e->getMessage());
            return false;
        }
    }

    private static function sendEmail(string $to, string $subject, string $html): bool
    {
        if (empty($to)) return false;
        $headers="MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\n";
        $from = getenv('OTP_EMAIL_FROM') ?: 'noreply@example.com';
        $headers.="From: $from\r\n";
        return @mail($to, $subject, $html, $headers);
    }

    private static function mask(string $s): string
    {
        if (strpos($s,'@')!==false) { [$a,$b] = explode('@',$s,2); return substr($a,0,2).'***@'.$b; }
        $d = preg_replace('/\D+/', '', $s);
        return substr($d,0,2).'***'.substr($d,-2);
    }

    private static function k(string $suffix): string
    {
        return PP_OTP_KEYS . '_' . $suffix;
    }

    /** Root path for file storage */
    private static function rootPath(): string
    {
        if (defined('BASE_PATH')) return BASE_PATH;
        if (defined('ROOT_PATH')) return ROOT_PATH;
        return rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__,2)), '/') . '/';
    }

    // ====== DB shims ======

    private static function db(): \PDO { global $pdo; return $pdo; }

    private static function dbOne(string $sql, array $p=[]): ?array
    {
        $st = self::db()->prepare($sql); $st->execute($p); $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function dbAll(string $sql, array $p=[]): array
    {
        $st = self::db()->prepare($sql); $st->execute($p); return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function dbExec(string $sql, array $p=[]): bool
    {
        $st = self::db()->prepare($sql); return $st->execute($p);
    }
}
