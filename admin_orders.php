<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (($_SESSION['shop_admin'] ?? false) !== true) {
    message('Administrator login required.');
    redirect_to('admin.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $next = (string) ($_POST['status'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));
    if ($id === false || $id === null || strlen($note) > 250) {
        message('Invalid order change or note.');
        redirect_to('admin_orders.php');
    }
    $started = false;
    try {
        if (!sqlsrv_begin_transaction(db())) throw new RuntimeException('Could not start status change.');
        $started = true;
        $rows = rows(sql('SELECT status FROM dbo.orders WITH (UPDLOCK, HOLDLOCK) WHERE id = ?', [$id]));
        if (!$rows || !in_array($next, status_next((string)$rows[0]['status']), true)) {
            throw new DomainException('Only the next valid delivery status is permitted.');
        }
        $stmt = sql('UPDATE dbo.orders SET status = ? WHERE id = ? AND status = ?',
                    [$next,$id,$rows[0]['status']]);
        $changed = sqlsrv_rows_affected($stmt);
        sqlsrv_free_stmt($stmt);
        if ($changed !== 1) throw new DomainException('Order changed; refresh and try again.');
        sql('INSERT INTO dbo.order_status_events(order_id,status,note) VALUES(?,?,?)',
            [$id,$next,$note !== '' ? $note : 'Updated by book administrator']);
        if (!sqlsrv_commit(db())) throw new RuntimeException('Could not save delivery status.');
        $started = false;
        message('Order tracking status updated.');
    } catch (Throwable $e) {
        if ($started) sqlsrv_rollback(db());
        if ($e instanceof DomainException) message($e->getMessage());
        else {
            error_log('Admin delivery tracking error: ' . $e->getMessage());
            message('Order could not be updated.');
        }
    }
    redirect_to('admin_orders.php');
}
try {
    $orders = rows(sql('SELECT TOP (100) id, customer_name, customer_email, total, status,
                              payment_status, created_at FROM dbo.orders ORDER BY id DESC'));
} catch (Throwable $e) {
    unavailable($e);
}
page_header('Manage simulated deliveries');
?>
<p><a href="admin.php">Back to book inventory</a></p>
<p class="notice">These delivery transitions are simulated and do not trigger shipping or real payment refunds.</p>
<div class="table-wrap"><table>
<thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Update</th></tr></thead>
<tbody><?php foreach ($orders as $order): ?>
<tr>
 <td>#<?= (int)$order['id'] ?></td>
 <td><?= h($order['customer_name']) ?><br><small><?= h($order['customer_email']) ?></small></td>
 <td><?= h(pounds(price_cents((string)$order['total']) ?? 0)) ?></td>
 <td><strong><?= h((string)$order['status']) ?></strong><br><small><?= h((string)$order['payment_status']) ?></small></td>
 <td>
 <?php $nextStates = status_next((string)$order['status']); ?>
 <?php if (!$nextStates): ?><span class="muted">Final state</span>
 <?php else: ?>
  <form method="post" class="admin-status-form">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
    <label for="state-<?= (int)$order['id'] ?>">Next status</label>
    <select id="state-<?= (int)$order['id'] ?>" name="status">
      <?php foreach ($nextStates as $candidate): ?>
        <option value="<?= h($candidate) ?>"><?= h(ucfirst($candidate)) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="note-<?= (int)$order['id'] ?>">Optional tracking note</label>
    <input id="note-<?= (int)$order['id'] ?>" name="note" maxlength="250">
    <button type="submit">Update order</button>
  </form>
 <?php endif; ?>
 </td>
</tr>
<?php endforeach; ?></tbody>
</table></div>
<?php page_footer(); ?>
