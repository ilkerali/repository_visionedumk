<?php
/**
 * Admin Publication Types Management Page
 * 
 * Yayın türlerini yönetme sayfası - Ekleme, düzenleme, silme
 * Manage publication types - Add, edit, delete
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');
requireAdmin('../dashboard.php');

$pdo = getDBConnection();
$error = '';
$success = '';
$editMode = false;
$editType = null;

// Düzenleme modunu kontrol et (Check edit mode)
if (isset($_GET['edit']) && isset($_GET['id'])) {
    $editMode = true;
    $typeId = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM publication_types WHERE type_id = :type_id");
        $stmt->execute([':type_id' => $typeId]);
        $editType = $stmt->fetch();
        
        if (!$editType) {
            $error = "Publication type not found!";
            $editMode = false;
        }
    } catch (PDOException $e) {
        error_log("Type fetch error: " . $e->getMessage());
        $error = "Error loading publication type.";
        $editMode = false;
    }
}

// Yayın türü ekleme/güncelleme (Add/Update publication type)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $typeCode = strtoupper(cleanInput($_POST['type_code'] ?? ''));
        $typeNameEn = cleanInput($_POST['type_name_en'] ?? '');
        $typeNameTr = cleanInput($_POST['type_name_tr'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $typeIdToUpdate = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
        
        // Validasyon (Validation)
        if (empty($typeCode) || empty($typeNameEn) || empty($typeNameTr)) {
            $error = "Type code and names are required!";
        } elseif (!preg_match('/^[A-Z_]+$/', $typeCode)) {
            $error = "Type code must contain only uppercase letters and underscores!";
        } else {
            try {
                if ($typeIdToUpdate > 0) {
                    // Güncelleme (Update)
                    // Kod değişikliği kontrolü (Check if code is being changed)
                    $stmtCheck = $pdo->prepare("SELECT type_code FROM publication_types WHERE type_id = :type_id");
                    $stmtCheck->execute([':type_id' => $typeIdToUpdate]);
                    $oldCode = $stmtCheck->fetchColumn();
                    
                    if ($oldCode !== $typeCode) {
                        // Kod değişiyorsa benzersizlik kontrolü (Check uniqueness if code is changing)
                        $stmtDuplicate = $pdo->prepare("SELECT COUNT(*) FROM publication_types WHERE type_code = :type_code AND type_id != :type_id");
                        $stmtDuplicate->execute([':type_code' => $typeCode, ':type_id' => $typeIdToUpdate]);
                        if ($stmtDuplicate->fetchColumn() > 0) {
                            $error = "This type code already exists!";
                        }
                    }
                    
                    if (empty($error)) {
                        $stmt = $pdo->prepare("
                            UPDATE publication_types 
                            SET type_code = :type_code,
                                type_name_en = :type_name_en,
                                type_name_tr = :type_name_tr,
                                description = :description,
                                is_active = :is_active
                            WHERE type_id = :type_id
                        ");
                        
                        $stmt->execute([
                            ':type_code' => $typeCode,
                            ':type_name_en' => $typeNameEn,
                            ':type_name_tr' => $typeNameTr,
                            ':description' => $description,
                            ':is_active' => $isActive,
                            ':type_id' => $typeIdToUpdate
                        ]);
                        
                        logActivity($pdo, $_SESSION['user_id'], 'type_update', 'publication_types', $typeIdToUpdate);
                        $success = "Publication type updated successfully!";
                        $editMode = false;
                    }
                } else {
                    // Yeni ekleme (Insert new)
                    // Benzersizlik kontrolü (Check uniqueness)
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM publication_types WHERE type_code = :type_code");
                    $stmtCheck->execute([':type_code' => $typeCode]);
                    
                    if ($stmtCheck->fetchColumn() > 0) {
                        $error = "This type code already exists!";
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO publication_types (type_code, type_name_en, type_name_tr, description, is_active)
                            VALUES (:type_code, :type_name_en, :type_name_tr, :description, :is_active)
                        ");
                        
                        $stmt->execute([
                            ':type_code' => $typeCode,
                            ':type_name_en' => $typeNameEn,
                            ':type_name_tr' => $typeNameTr,
                            ':description' => $description,
                            ':is_active' => $isActive
                        ]);
                        
                        $newTypeId = $pdo->lastInsertId();
                        logActivity($pdo, $_SESSION['user_id'], 'type_create', 'publication_types', $newTypeId);
                        $success = "Publication type created successfully!";
                        $_POST = []; // Formu temizle (Clear form)
                    }
                }
            } catch (PDOException $e) {
                error_log("Type save error: " . $e->getMessage());
                $error = "Error saving publication type. Please try again.";
            }
        }
    }
}

// Yayın türü silme (Delete publication type)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $typeId = (int)$_GET['id'];
    
    try {
        // Kullanımda mı kontrol et (Check if in use)
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM publications WHERE type_id = :type_id");
        $stmtCheck->execute([':type_id' => $typeId]);
        $usageCount = $stmtCheck->fetchColumn();
        
        if ($usageCount > 0) {
            $error = "Cannot delete! This type is used in $usageCount publication(s). Please deactivate it instead.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM publication_types WHERE type_id = :type_id");
            $stmt->execute([':type_id' => $typeId]);
            
            logActivity($pdo, $_SESSION['user_id'], 'type_delete', 'publication_types', $typeId);
            $success = "Publication type deleted successfully!";
        }
    } catch (PDOException $e) {
        error_log("Type delete error: " . $e->getMessage());
        $error = "Error deleting publication type.";
    }
}

// Aktif/Pasif yapma (Toggle active status)
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $typeId = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE publication_types SET is_active = NOT is_active WHERE type_id = :type_id");
        $stmt->execute([':type_id' => $typeId]);
        
        logActivity($pdo, $_SESSION['user_id'], 'type_toggle', 'publication_types', $typeId);
        $success = "Publication type status updated successfully!";
    } catch (PDOException $e) {
        error_log("Type toggle error: " . $e->getMessage());
        $error = "Error updating publication type status.";
    }
}

// Tüm yayın türlerini getir (Fetch all publication types)
try {
    $stmt = $pdo->query("
        SELECT pt.*, 
               COUNT(p.publication_id) as publication_count
        FROM publication_types pt
        LEFT JOIN publications p ON pt.type_id = p.type_id
        GROUP BY pt.type_id
        ORDER BY pt.type_name_en
    ");
    $types = $stmt->fetchAll();
    
    // İstatistikler (Statistics)
    $totalTypes = count($types);
    $activeTypes = count(array_filter($types, function($t) { return $t['is_active']; }));
    $totalPublications = array_sum(array_column($types, 'publication_count'));
    
} catch (PDOException $e) {
    error_log("Types fetch error: " . $e->getMessage());
    $types = [];
    $totalTypes = 0;
    $activeTypes = 0;
    $totalPublications = 0;
}

// Sayfa başlığı (Page title)
$page_title = "Publication Types Management";
include 'admin_header.php';
?>

<!-- Sayfa Başlığı (Page header) -->
<div class="page-header">
    <div>
        <h1>Publication Types Management</h1>
        <p class="text-muted">Manage publication types and categories</p>
    </div>
</div>

<!-- Alert Mesajları (Alert messages) -->
<?php if ($error): ?>
    <div class="alert alert-error">
        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <?php echo sanitize($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <?php echo sanitize($success); ?>
    </div>
<?php endif; ?>

<!-- İstatistik Kartları (Statistics cards) -->
<div class="stats-grid stats-grid-3">
    <div class="stat-card stat-primary">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="8" y1="6" x2="21" y2="6"></line>
                <line x1="8" y1="12" x2="21" y2="12"></line>
                <line x1="8" y1="18" x2="21" y2="18"></line>
                <line x1="3" y1="6" x2="3.01" y2="6"></line>
                <line x1="3" y1="12" x2="3.01" y2="12"></line>
                <line x1="3" y1="18" x2="3.01" y2="18"></line>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $totalTypes; ?></h3>
            <p>Total Types</p>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $activeTypes; ?></h3>
            <p>Active Types</p>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $totalPublications; ?></h3>
            <p>Total Publications</p>
        </div>
    </div>
</div>

<div class="dashboard-row">
    <!-- Sol Kolon: Form (Left column: Form) -->
    <div class="dashboard-col-6">
        <div class="form-section">
            <h2 class="form-section-title">
                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <?php if ($editMode): ?>
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    <?php else: ?>
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    <?php endif; ?>
                </svg>
                <?php echo $editMode ? 'Edit Publication Type' : 'Add New Publication Type'; ?>
            </h2>

            <form method="POST" action="publication_types.php">
                <input type="hidden" name="action" value="save">
                <?php if ($editMode && $editType): ?>
                    <input type="hidden" name="type_id" value="<?php echo $editType['type_id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="type_code">
                        Type Code <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="type_code" 
                        name="type_code" 
                        value="<?php echo $editMode && $editType ? sanitize($editType['type_code']) : (isset($_POST['type_code']) ? sanitize($_POST['type_code']) : ''); ?>"
                        required
                        pattern="[A-Z_]+"
                        placeholder="ARTICLE"
                        style="text-transform: uppercase;"
                    >
                    <small class="form-help">Uppercase letters and underscores only (e.g., ARTICLE, CONFERENCE, BOOK_CHAPTER)</small>
                </div>

                <div class="form-group">
                    <label for="type_name_en">
                        Type Name (English) <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="type_name_en" 
                        name="type_name_en" 
                        value="<?php echo $editMode && $editType ? sanitize($editType['type_name_en']) : (isset($_POST['type_name_en']) ? sanitize($_POST['type_name_en']) : ''); ?>"
                        required
                        placeholder="Journal Article"
                    >
                </div>

                <div class="form-group">
                    <label for="type_name_tr">
                        Type Name (Turkish) <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="type_name_tr" 
                        name="type_name_tr" 
                        value="<?php echo $editMode && $editType ? sanitize($editType['type_name_tr']) : (isset($_POST['type_name_tr']) ? sanitize($_POST['type_name_tr']) : ''); ?>"
                        required
                        placeholder="Dergi Makalesi"
                    >
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        rows="4"
                        placeholder="Brief description of this publication type..."
                    ><?php echo $editMode && $editType ? sanitize($editType['description']) : (isset($_POST['description']) ? sanitize($_POST['description']) : ''); ?></textarea>
                </div>

                <div class="form-group">
                    <div class="checkbox-label">
                        <input 
                            type="checkbox" 
                            id="is_active" 
                            name="is_active"
                            <?php echo ($editMode && $editType) ? ($editType['is_active'] ? 'checked' : '') : 'checked'; ?>
                        >
                        <span>Active (Available for selection)</span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <?php echo $editMode ? 'Update Type' : 'Add Type'; ?>
                    </button>
                    <?php if ($editMode): ?>
                        <a href="publication_types.php" class="btn btn-secondary">Cancel Edit</a>
                    <?php endif; ?>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sağ Kolon: Liste (Right column: List) -->
    <div class="dashboard-col-6">
        <div class="card">
            <div class="card-header">
                <h2>Publication Types (<?php echo count($types); ?>)</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($types)): ?>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
                        <?php foreach ($types as $type): ?>
                        <div style="padding: var(--spacing-lg); background: var(--gray-50); border-radius: var(--radius-lg); border-left: 4px solid var(--primary-color);">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--spacing-sm);">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: var(--spacing-sm); margin-bottom: var(--spacing-xs);">
                                        <span class="type-badge badge-<?php echo strtolower($type['type_code']); ?>">
                                            <?php echo sanitize($type['type_code']); ?>
                                        </span>
                                        <?php if (!$type['is_active']): ?>
                                            <span class="status-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <h4 style="font-size: 1rem; margin-bottom: 0.25rem; color: var(--gray-900);">
                                        <?php echo sanitize($type['type_name_en']); ?>
                                    </h4>
                                    <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: var(--spacing-xs);">
                                        <?php echo sanitize($type['type_name_tr']); ?>
                                    </p>
                                    <?php if ($type['description']): ?>
                                        <p style="font-size: 0.8125rem; color: var(--gray-600); font-style: italic;">
                                            <?php echo sanitize($type['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <div style="margin-top: var(--spacing-sm);">
                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.8125rem; color: var(--primary-color); font-weight: 600;">
                                            <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                <polyline points="14 2 14 8 20 8"></polyline>
                                            </svg>
                                            <?php echo $type['publication_count']; ?> publication(s)
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: var(--spacing-sm); margin-top: var(--spacing-md); padding-top: var(--spacing-md); border-top: 1px solid var(--gray-200);">
                                <a href="publication_types.php?edit=1&id=<?php echo $type['type_id']; ?>" 
                                   class="btn btn-sm btn-secondary"
                                   style="flex: 1;">
                                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    Edit
                                </a>
                                
                                <a href="publication_types.php?action=toggle&id=<?php echo $type['type_id']; ?>" 
                                   class="btn btn-sm btn-secondary"
                                   onclick="return confirm('Are you sure you want to <?php echo $type['is_active'] ? 'deactivate' : 'activate'; ?> this type?');">
                                    <?php if ($type['is_active']): ?>
                                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                        </svg>
                                        Deactivate
                                    <?php else: ?>
                                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                        </svg>
                                        Activate
                                    <?php endif; ?>
                                </a>
                                
                                <?php if ($type['publication_count'] == 0): ?>
                                    <a href="publication_types.php?action=delete&id=<?php echo $type['type_id']; ?>" 
                                       class="btn btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this type? This action cannot be undone.');"
                                       style="background: var(--error-color); color: white;">
                                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                        Delete
                                    </a>
                                <?php else: ?>
                                    <button 
                                        class="btn btn-sm"
                                        disabled
                                        title="Cannot delete - type is in use"
                                        style="background: var(--gray-300); color: var(--gray-600); cursor: not-allowed;">
                                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                        In Use
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                            <line x1="3" y1="6" x2="3.01" y2="6"></line>
                            <line x1="3" y1="12" x2="3.01" y2="12"></line>
                            <line x1="3" y1="18" x2="3.01" y2="18"></line>
                        </svg>
                        <h3>No Publication Types</h3>
                        <p>No publication types have been created yet. Use the form to add your first type.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
