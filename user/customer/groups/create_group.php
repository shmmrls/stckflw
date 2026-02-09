<?php
require_once __DIR__ . '/../../../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();
$error = '';
$success = '';

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $group_name = trim($_POST['group_name']);
    $group_type = $_POST['group_type'];
    
    if (empty($group_name)) {
        $error = "Group name is required";
    } else {
        // Generate unique invitation code
        $invitation_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        
        // Create group
        $stmt = $conn->prepare("INSERT INTO groups (group_name, group_type, created_by, invitation_code) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $group_name, $group_type, $user_id, $invitation_code);
        
        if ($stmt->execute()) {
            $group_id = $conn->insert_id;
            
            // Add creator as member
            $member_stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id, member_role) VALUES (?, ?, 'parent')");
            $member_stmt->bind_param("ii", $group_id, $user_id);
            $member_stmt->execute();
            
            $success = "Group created successfully! Invitation Code: <strong>$invitation_code</strong>";
        } else {
            $error = "Failed to create group";
        }
    }
}

// Handle joining group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_group'])) {
    $invitation_code = strtoupper(trim($_POST['invitation_code']));
    
    if (empty($invitation_code)) {
        $error = "Invitation code is required";
    } else {
        // Find group
        $stmt = $conn->prepare("SELECT group_id FROM groups WHERE invitation_code = ?");
        $stmt->bind_param("s", $invitation_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Invalid invitation code";
        } else {
            $group = $result->fetch_assoc();
            $group_id = $group['group_id'];
            
            // Check if already member
            $check_stmt = $conn->prepare("SELECT member_id FROM group_members WHERE group_id = ? AND user_id = ?");
            $check_stmt->bind_param("ii", $group_id, $user_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "You are already a member of this group";
            } else {
                // Add as member
                $add_stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id, member_role) VALUES (?, ?, 'member')");
                $add_stmt->bind_param("ii", $group_id, $user_id);
                
                if ($add_stmt->execute()) {
                    $success = "Successfully joined the group!";
                } else {
                    $error = "Failed to join group";
                }
            }
        }
    }
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/create_group.css">';
require_once __DIR__ . '/../../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="create-group-page">
    <div class="create-group-container">
        
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Create or Join Group</h1>
                    <p class="page-subtitle">Start collaborating or join an existing inventory group</p>
                </div>
                <div class="header-actions">
                    <a href="my_groups.php" class="btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Back to Groups
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="forms-grid">
            <!-- Create Group -->
            <div class="form-section">
                <div class="section-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                </div>
                
                <div class="section-header">
                    <h2 class="section-title">Create New Group</h2>
                    <p class="section-description">Set up a new group and invite members to collaborate on inventory management</p>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="group_name">Group Name *</label>
                        <input type="text" 
                               id="group_name" 
                               name="group_name" 
                               placeholder="e.g., Smith Family, Office Kitchen" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="group_type">Group Type *</label>
                        <select id="group_type" name="group_type" required>
                            <option value="household">Household</option>
                            <option value="co_living">Co-living</option>
                            <option value="small_business">Small Business</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="create_group" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Create Group
                    </button>
                </form>
                
                <div class="info-box">
                    <div class="info-box-title">What happens next?</div>
                    <p class="info-box-text">After creating your group, you'll receive a unique invitation code that you can share with others to invite them to join.</p>
                </div>
            </div>
            
            <!-- Join Group -->
            <div class="form-section">
                <div class="section-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
                    </svg>
                </div>
                
                <div class="section-header">
                    <h2 class="section-title">Join Existing Group</h2>
                    <p class="section-description">Enter an invitation code to join a group that has already been created</p>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="invitation_code">Invitation Code *</label>
                        <input type="text" 
                               id="invitation_code" 
                               name="invitation_code" 
                               placeholder="Enter 8-character code" 
                               maxlength="8"
                               style="text-transform: uppercase; font-family: 'Courier New', monospace; letter-spacing: 2px;"
                               required>
                    </div>
                    
                    <button type="submit" name="join_group" class="btn-primary btn-success">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                        Join Group
                    </button>
                </form>
                
                <div class="info-box">
                    <div class="info-box-title">How to join</div>
                    <p class="info-box-text">Ask the group creator for their unique 8-character invitation code and enter it above. You'll be added as a member immediately.</p>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // Auto-uppercase invitation code
    document.getElementById('invitation_code').addEventListener('input', function(e) {
        e.target.value = e.target.value.toUpperCase();
    });
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php $conn->close(); ?>