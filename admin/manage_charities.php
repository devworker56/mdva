<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
include '../includes/header.php';

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get pending charities
$query = "SELECT * FROM charities WHERE approved = 0 ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_charities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved charities
$query = "SELECT * FROM charities WHERE approved = 1 ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$approved_charities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Manage Charities</h2>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Pending Approval</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($pending_charities)): ?>
                        <p class="text-muted">No pending charities for approval.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Website</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pending_charities as $charity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($charity['name']); ?></td>
                                        <td><?php echo htmlspecialchars($charity['email']); ?></td>
                                        <td><?php echo htmlspecialchars($charity['website']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($charity['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-success btn-sm" onclick="approveCharity(<?php echo $charity['id']; ?>)">Approve</button>
                                            <button class="btn btn-danger btn-sm" onclick="rejectCharity(<?php echo $charity['id']; ?>)">Reject</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Approved Charities</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Website</th>
                                    <th>Approved</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($approved_charities as $charity): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($charity['name']); ?></td>
                                    <td><?php echo htmlspecialchars($charity['email']); ?></td>
                                    <td><?php echo htmlspecialchars($charity['website']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($charity['updated_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="revokeCharity(<?php echo $charity['id']; ?>)">Revoke</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function approveCharity(charityId) {
    if(confirm('Are you sure you want to approve this charity?')) {
        fetch('../api/charities.php?action=approve&id=' + charityId)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
}

function rejectCharity(charityId) {
    if(confirm('Are you sure you want to reject this charity?')) {
        fetch('../api/charities.php?action=reject&id=' + charityId)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
}

function revokeCharity(charityId) {
    if(confirm('Are you sure you want to revoke this charity\'s approval?')) {
        fetch('../api/charities.php?action=revoke&id=' + charityId)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
}
</script>

<?php include '../includes/footer.php'; ?>