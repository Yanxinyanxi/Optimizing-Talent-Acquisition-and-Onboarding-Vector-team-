<?php
// Simple chat log viewer (HR only)
require_once 'includes/config.php';
require_once 'includes/auth.php';

requireRole('hr');

$stmt = $connection->prepare("
    SELECT cc.*, u.full_name, u.department 
    FROM chat_conversations cc
    JOIN users u ON cc.user_id = u.id
    ORDER BY cc.created_at DESC
    LIMIT 100
");
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!-- Display conversations in a table -->
<table>
    <tr>
        <th>Date</th>
        <th>Employee</th>
        <th>Department</th>
        <th>Message</th>
        <th>Response</th>
        <th>Response Time</th>
    </tr>
    <?php foreach ($conversations as $conv): ?>
    <tr>
        <td><?php echo date('M j, Y g:i A', strtotime($conv['created_at'])); ?></td>
        <td><?php echo htmlspecialchars($conv['full_name']); ?></td>
        <td><?php echo htmlspecialchars($conv['department']); ?></td>
        <td><?php echo htmlspecialchars($conv['message']); ?></td>
        <td><?php echo htmlspecialchars($conv['response']); ?></td>
        <td><?php echo $conv['api_response_time'] ? round($conv['api_response_time'], 3) . 's' : 'N/A'; ?></td>
    </tr>
    <?php endforeach; ?>
</table>