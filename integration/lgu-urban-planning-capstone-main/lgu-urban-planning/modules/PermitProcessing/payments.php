<?php
/**
 * payments.php
 *
 * Small helpers shared between:
 *   - admin/view.php        (creates the unpaid payment record when staff
 *                             move an application to 'pending_payment')
 *   - modules/PermitProcessing/pay.php  (the mock checkout page the
 *                             applicant uses to "pay" and trigger permit
 *                             issuance)
 *
 * This is a MOCK payment flow built for a capstone demo — no real money or
 * third-party gateway is involved. "Pay Now" simply flips a DB row after a
 * simulated delay. Swap the internals of markPaymentPaid()'s caller
 * (pay.php) for a real gateway integration later without touching the rest
 * of the approval flow, since everything downstream keys off payments.status.
 */

/**
 * Generates a short, human-shareable reference number for a payment,
 * e.g. "PAY-7F3K9QZC". Not guaranteed unique on its own — the caller
 * should retry on a unique-constraint violation against
 * payments.reference_number (rare, but possible).
 */
function generatePaymentReference(): string
{
    return 'PAY-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
}

/**
 * Generates a mock transaction id to stand in for what a real gateway
 * would return on successful payment, e.g. "TXN-A1B2C3D4".
 */
function generateMockTransactionId(): string
{
    return 'TXN-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
}

/**
 * Creates the 'unpaid' payment row for an application once staff move it
 * to 'pending_payment'. Safe to call more than once — if an unpaid or paid
 * payment already exists for this application, that existing row is
 * returned instead of creating a duplicate.
 *
 * @param PDO   $dbConn
 * @param int   $applicationId
 * @param float $amount  Fixed fee amount, e.g. PERMIT_FEE_AMOUNT
 * @return array The payment row (existing or newly created)
 */
function createPendingPayment(PDO $dbConn, int $applicationId, float $amount): array
{
    $existing = $dbConn->prepare("SELECT * FROM payments WHERE application_id = ? ORDER BY id DESC LIMIT 1");
    $existing->execute([$applicationId]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        return $row;
    }

    $reference = generatePaymentReference();

    $stmt = $dbConn->prepare(
        "INSERT INTO payments (application_id, amount, status, reference_number, created_at)
         VALUES (?, ?, 'unpaid', ?, NOW())"
    );
    $stmt->execute([$applicationId, $amount, $reference]);

    $newId = (int) $dbConn->lastInsertId();
    $fetch = $dbConn->prepare("SELECT * FROM payments WHERE id = ?");
    $fetch->execute([$newId]);
    return $fetch->fetch(PDO::FETCH_ASSOC);
}