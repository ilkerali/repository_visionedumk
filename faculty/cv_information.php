<?php
/**
 * Faculty CV Information Page
 * 
 * Öğretim üyelerinin CV bilgilerini girmesi için sayfa
 * Page for faculty members to enter their CV information
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Kullanıcı bilgilerini al
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$user = $stmt->fetch();

// Mevcut CV verilerini çek
try {
    // CV Profile
    $stmt = $pdo->prepare("SELECT * FROM cv_profiles WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $profile = $stmt->fetch();
    
    // Web Links
    $stmt = $pdo->prepare("SELECT * FROM cv_web_links WHERE user_id = :user_id ORDER BY display_order");
    $stmt->execute([':user_id' => $userId]);
    $webLinks = $stmt->fetchAll();
    
    // Work Experience
    $stmt = $pdo->prepare("SELECT * FROM cv_work_experience WHERE user_id = :user_id ORDER BY start_date DESC");
    $stmt->execute([':user_id' => $userId]);
    $workExperience = $stmt->fetchAll();
    
    // Education
    $stmt = $pdo->prepare("SELECT * FROM cv_education WHERE user_id = :user_id ORDER BY graduation_year DESC");
    $stmt->execute([':user_id' => $userId]);
    $education = $stmt->fetchAll();
    
    // Languages
    $stmt = $pdo->prepare("SELECT * FROM cv_language_skills WHERE user_id = :user_id ORDER BY display_order");
    $stmt->execute([':user_id' => $userId]);
    $languages = $stmt->fetchAll();
    
    // Personal Skills
    $stmt = $pdo->prepare("SELECT * FROM cv_personal_skills WHERE user_id = :user_id ORDER BY display_order");
    $stmt->execute([':user_id' => $userId]);
    $personalSkills = $stmt->fetchAll();
    
    // Additional Info
    $stmt = $pdo->prepare("SELECT * FROM cv_additional_info WHERE user_id = :user_id ORDER BY display_order");
    $stmt->execute([':user_id' => $userId]);
    $additionalInfo = $stmt->fetchAll();
    
    // Driving Licenses
    $stmt = $pdo->prepare("SELECT * FROM cv_driving_licenses WHERE user_id = :user_id ORDER BY display_order");
    $stmt->execute([':user_id' => $userId]);
    $drivingLicenses = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("CV data fetch error: " . $e->getMessage());
    $profile = null;
    $webLinks = [];
    $workExperience = [];
    $education = [];
    $languages = [];
    $personalSkills = [];
    $additionalInfo = [];
    $drivingLicenses = [];
}

// Sayfa başlığı
$page_title = "My CV Information";
include 'faculty_header.php';
?>

<style>
.cv-section {
    background: white;
    border-radius: var(--radius-lg);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    box-shadow: var(--shadow-sm);
}

.cv-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 2px solid var(--primary-color);
}

.cv-section-header h2 {
    color: var(--primary-color);
    margin: 0;
    font-size: 1.5rem;
}

.cv-section-header .btn {
    font-size: 0.875rem;
}

.cv-item {
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-md);
    position: relative;
}

.cv-item:hover {
    border-color: var(--primary-color);
    box-shadow: var(--shadow-sm);
}

.cv-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--spacing-md);
}

.cv-item-title {
    font-weight: 600;
    color: var(--gray-900);
    font-size: 1.125rem;
}

.cv-item-subtitle {
    color: var(--gray-600);
    margin-top: var(--spacing-xs);
}

.cv-item-actions {
    display: flex;
    gap: var(--spacing-xs);
}

.btn-icon-small {
    padding: var(--spacing-xs) var(--spacing-sm);
    font-size: 0.875rem;
    min-width: auto;
}

.cv-item-content {
    color: var(--gray-700);
    line-height: 1.6;
}

.cv-item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    margin-top: var(--spacing-sm);
    font-size: 0.875rem;
    color: var(--gray-600);
}

.cv-item-meta-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.empty-state-small {
    text-align: center;
    padding: var(--spacing-xl);
    color: var(--gray-500);
    font-style: italic;
}

.language-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: var(--spacing-md);
}

.language-table th,
.language-table td {
    padding: var(--spacing-sm);
    border: 1px solid var(--gray-200);
    text-align: center;
}

.language-table th {
    background: var(--gray-100);
    font-weight: 600;
    font-size: 0.875rem;
}

.language-table td:first-child {
    text-align: left;
    font-weight: 600;
}

.badge-container {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-xs);
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: white;
    margin: 2% auto;
    padding: 0;
    border-radius: var(--radius-lg);
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: var(--shadow-lg);
}

.modal-header {
    padding: var(--spacing-lg) var(--spacing-xl);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--gray-900);
}

.modal-body {
    padding: var(--spacing-xl);
}

.modal-footer {
    padding: var(--spacing-lg) var(--spacing-xl);
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-sm);
}

.close {
    font-size: 2rem;
    font-weight: 300;
    color: var(--gray-500);
    cursor: pointer;
    border: none;
    background: none;
    padding: 0;
    line-height: 1;
}

.close:hover {
    color: var(--gray-900);
}

.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-md);
}

.form-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: var(--spacing-md);
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    background: var(--gray-50);
    border-radius: var(--radius-md);
}

.checkbox-group input[type="checkbox"] {
    width: auto;
    margin: 0;
}

.visibility-toggle {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: 0.875rem;
    color: var(--gray-600);
}

@media (max-width: 768px) {
    .form-grid-2,
    .form-grid-3 {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 5% auto;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>My CV Information</h1>
        <p class="text-muted">Manage your academic curriculum vitae</p>
    </div>
    <div style="display: flex; gap: var(--spacing-sm);">
        <a href="cv_preview.php" class="btn btn-secondary">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            Preview CV
        </a>
        <a href="cv_export_pdf.php" class="btn btn-primary">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Export PDF
        </a>
    </div>
</div>

<!-- Alert Messages -->
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

<!-- Personal Information Section -->
<div class="cv-section">
    <div class="cv-section-header">
        <h2>
            <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Personal Information
        </h2>
        <button class="btn btn-primary" onclick="editPersonalInfo()">
            <?php echo $profile ? 'Edit' : 'Add'; ?> Information
        </button>
    </div>
    
    <div style="display: flex; gap: var(--spacing-xl); align-items: flex-start; flex-wrap: wrap;">
        <!-- Profile Photo -->
        <div style="flex-shrink: 0;">
            <?php 
            $profileImage = '';
            if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])) {
                $profileImage = '../' . $user['profile_image'];
            } else {
                $profileImage = 'https://via.placeholder.com/150x180/CCCCCC/666666?text=Photo';
            }
            ?>
            <div style="text-align: center;">
                <img id="profilePhotoPreview" 
                     src="<?php echo $profileImage; ?>" 
                     alt="Profile Photo" 
                     style="width: 150px; height: 180px; object-fit: cover; border: 3px solid var(--primary-color); border-radius: 8px; margin-bottom: var(--spacing-sm);">
                <form id="photoUploadForm" enctype="multipart/form-data" style="margin-top: var(--spacing-sm);">
                    <input type="file" 
                           id="profilePhotoInput" 
                           name="profile_photo" 
                           accept="image/jpeg,image/png,image/jpg"
                           style="display: none;"
                           onchange="uploadProfilePhoto(this)">
                    <button type="button" 
                            onclick="document.getElementById('profilePhotoInput').click()" 
                            class="btn btn-secondary btn-sm"
                            style="width: 100%;">
                        📷 Change Photo
                    </button>
                </form>
                <small style="color: var(--gray-600); font-size: 0.75rem; display: block; margin-top: var(--spacing-xs);">
                    JPG, PNG (max 2MB)
                </small>
            </div>
        </div>
        
        <!-- Personal Info Details -->
        <div style="flex: 1; min-width: 300px;">
    
    <?php if ($profile): ?>
        <div class="cv-item">
            <div class="form-grid-2">
                <div>
                    <strong>Date of Birth:</strong><br>
                    <?php echo $profile['date_of_birth'] ? date('F j, Y', strtotime($profile['date_of_birth'])) : 'Not specified'; ?>
                </div>
                <div>
                    <strong>Place of Birth:</strong><br>
                    <?php echo sanitize($profile['place_of_birth']) ?: 'Not specified'; ?>
                </div>
                <div>
                    <strong>Nationality:</strong><br>
                    <?php echo sanitize($profile['nationality']) ?: 'Not specified'; ?>
                </div>
                <div>
                    <strong>Gender:</strong><br>
                    <?php echo sanitize($profile['gender']) ?: 'Not specified'; ?>
                </div>
                <div>
                    <strong>Phone:</strong><br>
                    <?php echo sanitize($profile['cv_phone']) ?: sanitize($user['phone']) ?: 'Not specified'; ?>
                </div>
                <div>
                    <strong>Email:</strong><br>
                    <?php echo sanitize($profile['cv_email']) ?: sanitize($user['email']); ?>
                </div>
                <div style="grid-column: 1 / -1;">
                    <strong>Address:</strong><br>
                    <?php echo sanitize($profile['cv_address']) ?: 'Not specified'; ?>
                    <?php if ($profile['cv_city'] || $profile['cv_postal_code'] || $profile['cv_country']): ?>
                        <br><?php echo sanitize($profile['cv_city']); ?> 
                        <?php echo sanitize($profile['cv_postal_code']); ?>, 
                        <?php echo sanitize($profile['cv_country']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state-small">
            No personal information added yet. Click "Add Information" to get started.
        </div>
    <?php endif; ?>
    </div><!-- Close flex wrapper -->
</div>

<!-- Web & Social Links Section -->
<div class="cv-section">
    <div class="cv-section-header">
        <h2>
            <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="2" y1="12" x2="22" y2="12"></line>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
            </svg>
            Web & Social Links
        </h2>
        <button class="btn btn-primary" onclick="addWebLink()">Add Link</button>
    </div>
    
    <?php if (!empty($webLinks)): ?>
        <?php foreach ($webLinks as $link): ?>
            <div class="cv-item">
                <div class="cv-item-header">
                    <div>
                        <div class="cv-item-title">
                            <span class="badge badge-primary"><?php echo sanitize($link['link_type']); ?></span>
                            <?php echo sanitize($link['link_label']); ?>
                        </div>
                        <div class="cv-item-subtitle">
                            <a href="<?php echo sanitize($link['link_url']); ?>" target="_blank">
                                <?php echo sanitize($link['link_url']); ?>
                            </a>
                        </div>
                    </div>
                    <div class="cv-item-actions">
                        <button class="btn btn-icon-small btn-secondary" onclick="editWebLink(<?php echo $link['link_id']; ?>)">
                            Edit
                        </button>
                        <button class="btn btn-icon-small btn-danger" onclick="deleteWebLink(<?php echo $link['link_id']; ?>)">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state-small">
            No web links added yet. Click "Add Link" to add your online profiles.
        </div>
    <?php endif; ?>
</div>

<!-- Work Experience Section -->
<div class="cv-section">
    <div class="cv-section-header">
        <h2>
            <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            Work Experience
        </h2>
        <button class="btn btn-primary" onclick="addWorkExperience()">Add Experience</button>
    </div>
    
    <?php if (!empty($workExperience)): ?>
        <?php foreach ($workExperience as $work): ?>
            <div class="cv-item">
                <div class="cv-item-header">
                    <div>
                        <div class="cv-item-title"><?php echo sanitize($work['job_title']); ?></div>
                        <div class="cv-item-subtitle">
                            <?php echo sanitize($work['employer']); ?>
                            <?php if ($work['location']): ?>
                                • <?php echo sanitize($work['location']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cv-item-actions">
                        <button class="btn btn-icon-small btn-secondary" onclick="editWorkExperience(<?php echo $work['experience_id']; ?>)">
                            Edit
                        </button>
                        <button class="btn btn-icon-small btn-danger" onclick="deleteWorkExperience(<?php echo $work['experience_id']; ?>)">
                            Delete
                        </button>
                    </div>
                </div>
                <div class="cv-item-content">
                    <div class="cv-item-meta">
                        <div class="cv-item-meta-item">
                            <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <?php echo date('M Y', strtotime($work['start_date'])); ?> - 
                            <?php echo $work['is_current'] ? 'Present' : date('M Y', strtotime($work['end_date'])); ?>
                        </div>
                        <?php if ($work['employment_type']): ?>
                            <div class="cv-item-meta-item">
                                <span class="badge badge-info"><?php echo sanitize($work['employment_type']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!$work['is_visible']): ?>
                            <div class="cv-item-meta-item">
                                <span class="badge badge-warning">Hidden</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($work['description']): ?>
                        <p style="margin-top: var(--spacing-md);">
                            <?php echo nl2br(sanitize($work['description'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state-small">
            No work experience added yet. Click "Add Experience" to add your employment history.
        </div>
    <?php endif; ?>
</div>

<!-- Education Section -->
<div class="cv-section">
    <div class="cv-section-header">
        <h2>
            <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
            Education & Training
        </h2>
        <button class="btn btn-primary" onclick="addEducation()">Add Education</button>
    </div>
    
    <?php if (!empty($education)): ?>
        <?php foreach ($education as $edu): ?>
            <div class="cv-item">
                <div class="cv-item-header">
                    <div>
                        <div class="cv-item-title"><?php echo sanitize($edu['degree']); ?></div>
                        <?php if ($edu['field_of_study']): ?>
                            <div class="cv-item-subtitle"><?php echo sanitize($edu['field_of_study']); ?></div>
                        <?php endif; ?>
                        <div class="cv-item-subtitle">
                            <?php echo sanitize($edu['institution']); ?>
                            <?php if ($edu['location']): ?>
                                • <?php echo sanitize($edu['location']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cv-item-actions">
                        <button class="btn btn-icon-small btn-secondary" onclick="editEducation(<?php echo $edu['education_id']; ?>)">
                            Edit
                        </button>
                        <button class="btn btn-icon-small btn-danger" onclick="deleteEducation(<?php echo $edu['education_id']; ?>)">
                            Delete
                        </button>
                    </div>
                </div>
                <div class="cv-item-content">
                    <div class="cv-item-meta">
                        <div class="cv-item-meta-item">
                            <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <?php echo $edu['graduation_year']; ?>
                        </div>
                        <?php if (!$edu['is_visible']): ?>
                            <div class="cv-item-meta-item">
                                <span class="badge badge-warning">Hidden</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($edu['thesis_title']): ?>
                        <p style="margin-top: var(--spacing-md);">
                            <strong>Thesis:</strong> <?php echo sanitize($edu['thesis_title']); ?>
                            <?php if ($edu['thesis_supervisor']): ?>
                                <br><strong>Supervisor:</strong> <?php echo sanitize($edu['thesis_supervisor']); ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state-small">
            No education records added yet. Click "Add Education" to add your qualifications.
        </div>
    <?php endif; ?>
</div>

<!-- Language Skills Section -->
<div class="cv-section">
    <div class="cv-section-header">
        <h2>
            <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
            </svg>
            Language Skills
        </h2>
        <button class="btn btn-primary" onclick="addLanguage()">Add Language</button>
    </div>
    
    <?php if (!empty($languages)): ?>
        <table class="language-table">
            <thead>
                <tr>
                    <th rowspan="2">Language</th>
                    <th colspan="2">Understanding</th>
                    <th colspan="2">Speaking</th>
                    <th rowspan="2">Writing</th>
                    <th rowspan="2">Actions</th>
                </tr>
                <tr>
                    <th>Listening</th>
                    <th>Reading</th>
                    <th>Interaction</th>
                    <th>Production</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($languages as $lang): ?>
                    <tr>
                        <td>
                            <?php echo sanitize($lang['language']); ?>
                            <?php if ($lang['is_mother_tongue']): ?>
                                <br><small style="color: var(--primary-color);">Mother tongue</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $lang['listening_level'] ?: '-'; ?></td>
                        <td><?php echo $lang['reading_level'] ?: '-'; ?></td>
                        <td><?php echo $lang['spoken_interaction_level'] ?: '-'; ?></td>
                        <td><?php echo $lang['spoken_production_level'] ?: '-'; ?></td>
                        <td><?php echo $lang['writing_level'] ?: '-'; ?></td>
                        <td>
                            <button class="btn btn-icon-small btn-secondary" onclick="editLanguage(<?php echo $lang['language_id']; ?>)" style="margin-right: 4px;">
                                Edit
                            </button>
                            <button class="btn btn-icon-small btn-danger" onclick="deleteLanguage(<?php echo $lang['language_id']; ?>)">
                                Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top: var(--spacing-md); font-size: 0.875rem; color: var(--gray-600);">
            <strong>CEFR Levels:</strong> A1, A2 (Basic) | B1, B2 (Independent) | C1, C2 (Proficient)
        </div>
    <?php else: ?>
        <div class="empty-state-small">
            No language skills added yet. Click "Add Language" to add your language proficiency.
        </div>
    <?php endif; ?>
</div>

<!-- Personal Skills Section -->
<div class="cv-section">
    <div class="cv-section-header">
        <h2>
            <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M12 20h9"></path>
                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
            </svg>
            Personal Skills
        </h2>
        <button class="btn btn-primary" onclick="addPersonalSkill()">Add Skill</button>
    </div>
    
    <?php if (!empty($personalSkills)): ?>
        <?php foreach ($personalSkills as $skill): ?>
            <div class="cv-item">
                <div class="cv-item-header">
                    <div>
                        <div class="cv-item-title">
                            <?php echo sanitize($skill['skill_category']); ?>
                            <?php if ($skill['skill_subcategory']): ?>
                                <span style="color: var(--gray-600);"> - <?php echo sanitize($skill['skill_subcategory']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cv-item-actions">
                        <button class="btn btn-icon-small btn-secondary" onclick="editPersonalSkill(<?php echo $skill['skill_id']; ?>)">
                            Edit
                        </button>
                        <button class="btn btn-icon-small btn-danger" onclick="deletePersonalSkill(<?php echo $skill['skill_id']; ?>)">
                            Delete
                        </button>
                    </div>
                </div>
                <div class="cv-item-content">
                    <div class="cv-item-meta">
                        <span class="badge badge-info"><?php echo sanitize($skill['skill_type']); ?></span>
                        <?php if ($skill['proficiency_level']): ?>
                            <span class="badge badge-success"><?php echo sanitize($skill['proficiency_level']); ?></span>
                        <?php endif; ?>
                        <?php if (!$skill['is_visible']): ?>
                            <span class="badge badge-warning">Hidden</span>
                        <?php endif; ?>
                    </div>
                    <p style="margin-top: var(--spacing-md);">
                        <?php echo nl2br(sanitize($skill['skill_description'])); ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state-small">
            No personal skills added yet. Click "Add Skill" to add your competencies.
        </div>
    <?php endif; ?>
</div>

<!-- Additional Information Section -->
<div class="cv-section">
    <div class="cv-section-header">
        <h2>
            <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            Additional Information
        </h2>
        <button class="btn btn-primary" onclick="addAdditionalInfo()">Add Information</button>
    </div>
    
    <?php if (!empty($additionalInfo)): ?>
        <?php foreach ($additionalInfo as $info): ?>
            <div class="cv-item">
                <div class="cv-item-header">
                    <div>
                        <div class="cv-item-title">
                            <?php echo sanitize($info['info_category']); ?>
                            <?php if ($info['info_subcategory']): ?>
                                <span style="color: var(--gray-600);"> - <?php echo sanitize($info['info_subcategory']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cv-item-actions">
                        <button class="btn btn-icon-small btn-secondary" onclick="editAdditionalInfo(<?php echo $info['info_id']; ?>)">
                            Edit
                        </button>
                        <button class="btn btn-icon-small btn-danger" onclick="deleteAdditionalInfo(<?php echo $info['info_id']; ?>)">
                            Delete
                        </button>
                    </div>
                </div>
                <div class="cv-item-content">
                    <div class="cv-item-meta">
                        <span class="badge badge-info"><?php echo sanitize($info['info_type']); ?></span>
                        <?php if ($info['start_date'] || $info['end_date']): ?>
                            <div class="cv-item-meta-item">
                                <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <?php if ($info['start_date']): ?>
                                    <?php echo date('Y', strtotime($info['start_date'])); ?>
                                    <?php if ($info['end_date']): ?>
                                        - <?php echo date('Y', strtotime($info['end_date'])); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!$info['is_visible']): ?>
                            <span class="badge badge-warning">Hidden</span>
                        <?php endif; ?>
                    </div>
                    <p style="margin-top: var(--spacing-md);">
                        <?php echo nl2br(sanitize($info['info_content'])); ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state-small">
            No additional information added yet. Click "Add Information" to add awards, certifications, etc.
        </div>
    <?php endif; ?>
</div>

<!-- Publications Section (from existing publications table) -->
<?php
// Fetch user's publications
$stmtPubs = $pdo->prepare("
    SELECT p.*, pt.type_name_en, pt.type_code
    FROM publications p
    JOIN publication_types pt ON p.type_id = pt.type_id
    WHERE p.user_id = :user_id
    ORDER BY p.publication_year DESC, p.created_at DESC
");
$stmtPubs->execute([':user_id' => $userId]);
$userPublications = $stmtPubs->fetchAll();

// Group by type
$pubsByType = [];
foreach ($userPublications as $pub) {
    $type = $pub['type_name_en'];
    if (!isset($pubsByType[$type])) {
        $pubsByType[$type] = [];
    }
    $pubsByType[$type][] = $pub;
}
?>

<div class="cv-section">
    <div class="cv-section-header">
        <h2>
            <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            Publications
        </h2>
        <div style="display: flex; gap: var(--spacing-sm);">
            <a href="my_publications.php" class="btn btn-secondary">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Manage Publications
            </a>
            <a href="add_publication.php" class="btn btn-primary">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Publication
            </a>
        </div>
    </div>
    
    <?php if (!empty($pubsByType)): ?>
        <?php foreach ($pubsByType as $type => $pubs): ?>
            <div style="margin-bottom: var(--spacing-xl);">
                <h3 style="color: var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: var(--spacing-xs); margin-bottom: var(--spacing-md);">
                    <?php echo sanitize($type); ?> (<?php echo count($pubs); ?>)
                </h3>
                
                <?php foreach ($pubs as $pub): ?>
                    <div class="cv-item">
                        <div class="cv-item-header">
                            <div style="flex: 1;">
                                <div class="cv-item-title"><?php echo sanitize($pub['title']); ?></div>
                                <div class="cv-item-subtitle">
                                    <?php echo sanitize($pub['authors']); ?>
                                </div>
                                <div class="cv-item-meta">
                                    <div class="cv-item-meta-item">
                                        <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <?php echo $pub['publication_year']; ?>
                                    </div>
                                    
                                    <?php if ($pub['journal_name']): ?>
                                        <div class="cv-item-meta-item">
                                            <strong>Journal:</strong> <?php echo sanitize($pub['journal_name']); ?>
                                            <?php if ($pub['volume']): ?>
                                                Vol. <?php echo $pub['volume']; ?>
                                            <?php endif; ?>
                                            <?php if ($pub['issue']): ?>
                                                (<?php echo $pub['issue']; ?>)
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($pub['conference_name']): ?>
                                        <div class="cv-item-meta-item">
                                            <strong>Conference:</strong> <?php echo sanitize($pub['conference_name']); ?>
                                        </div>
                                    <?php elseif ($pub['publisher']): ?>
                                        <div class="cv-item-meta-item">
                                            <strong>Publisher:</strong> <?php echo sanitize($pub['publisher']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($pub['doi']): ?>
                                        <div class="cv-item-meta-item">
                                            <strong>DOI:</strong> <?php echo sanitize($pub['doi']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="cv-item-actions">
                                <a href="view_publication.php?id=<?php echo $pub['publication_id']; ?>" 
                                   class="btn btn-icon-small btn-secondary" 
                                   title="View Details">
                                    View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state-small">
            No publications added yet. Click "Add Publication" to add your research outputs.
            <br><br>
            <a href="add_publication.php" class="btn btn-primary">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Your First Publication
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Driving Licenses Section -->
<div class="cv-section">
    <div class="cv-section-header">
        <h2>
            <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="1" y="3" width="15" height="13"></rect>
                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                <circle cx="18.5" cy="18.5" r="2.5"></circle>
            </svg>
            Driving Licenses
        </h2>
        <button class="btn btn-primary" onclick="addDrivingLicense()">Add License</button>
    </div>
    
    <?php if (!empty($drivingLicenses)): ?>
        <div class="badge-container" style="padding: var(--spacing-md);">
            <?php foreach ($drivingLicenses as $license): ?>
                <div class="badge badge-primary" style="font-size: 1rem; padding: var(--spacing-sm) var(--spacing-md); position: relative;">
                    Category <?php echo sanitize($license['license_category']); ?>
                    <button onclick="deleteDrivingLicense(<?php echo $license['license_id']; ?>)" 
                            style="margin-left: 8px; background: none; border: none; color: white; cursor: pointer; font-weight: bold;">
                        ×
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state-small">
            No driving licenses added yet. Click "Add License" to add your driving categories.
        </div>
    <?php endif; ?>
</div>

<!-- Modals will be added via JavaScript -->
<div id="modalContainer"></div>

<script>
// Modal yönetimi
function showModal(title, content, onSave) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.id = 'dynamicModal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                ${content}
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveModalData()">Save</button>
            </div>
        </div>
    `;
    
    document.getElementById('modalContainer').innerHTML = '';
    document.getElementById('modalContainer').appendChild(modal);
    modal.style.display = 'block';
    
    // Save callback'i kaydet
    window.currentModalSave = onSave;
    
    // Modal dışına tıklayınca kapat
    modal.onclick = function(event) {
        if (event.target === modal) {
            closeModal();
        }
    };
}

function closeModal() {
    const modal = document.getElementById('dynamicModal');
    if (modal) {
        modal.style.display = 'none';
        modal.remove();
    }
}

function saveModalData() {
    if (window.currentModalSave) {
        window.currentModalSave();
    }
}

// Personal Information
function editPersonalInfo() {
    const content = `
        <form id="personalInfoForm">
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?php echo $profile['date_of_birth'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label>Place of Birth</label>
                    <input type="text" name="place_of_birth" value="<?php echo sanitize($profile['place_of_birth'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Nationality</label>
                    <input type="text" name="nationality" value="<?php echo sanitize($profile['nationality'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">Select...</option>
                        <option value="Male" <?php echo ($profile['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($profile['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($profile['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone (override)</label>
                    <input type="tel" name="cv_phone" value="<?php echo sanitize($profile['cv_phone'] ?? ''); ?>" 
                           placeholder="Leave empty to use default: <?php echo sanitize($user['phone']); ?>">
                </div>
                <div class="form-group">
                    <label>Email (override)</label>
                    <input type="email" name="cv_email" value="<?php echo sanitize($profile['cv_email'] ?? ''); ?>"
                           placeholder="Leave empty to use default: <?php echo sanitize($user['email']); ?>">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Address</label>
                    <input type="text" name="cv_address" value="<?php echo sanitize($profile['cv_address'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="cv_city" value="<?php echo sanitize($profile['cv_city'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Postal Code</label>
                    <input type="text" name="cv_postal_code" value="<?php echo sanitize($profile['cv_postal_code'] ?? ''); ?>">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Country</label>
                    <input type="text" name="cv_country" value="<?php echo sanitize($profile['cv_country'] ?? ''); ?>">
                </div>
            </div>
        </form>
    `;
    
    showModal('Personal Information', content, function() {
        const formData = new FormData(document.getElementById('personalInfoForm'));
        formData.append('action', 'save_personal_info');
        
        fetch('cv_information_save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error saving data');
            console.error(error);
        });
    });
}

// Web Links
function addWebLink() {
    editWebLink(null);
}

function editWebLink(linkId) {
    // AJAX ile mevcut veriyi çek
    if (linkId) {
        fetch(`cv_information_save.php?action=get_web_link&id=${linkId}`)
            .then(response => response.json())
            .then(data => {
                showWebLinkModal(data);
            });
    } else {
        showWebLinkModal(null);
    }
}

function showWebLinkModal(data) {
    const content = `
        <form id="webLinkForm">
            <input type="hidden" name="link_id" value="${data ? data.link_id : ''}">
            <div class="form-group">
                <label>Link Type *</label>
                <select name="link_type" required>
                    <option value="">Select type...</option>
                    <option value="Personal Website" ${data && data.link_type === 'Personal Website' ? 'selected' : ''}>Personal Website</option>
                    <option value="LinkedIn" ${data && data.link_type === 'LinkedIn' ? 'selected' : ''}>LinkedIn</option>
                    <option value="ResearchGate" ${data && data.link_type === 'ResearchGate' ? 'selected' : ''}>ResearchGate</option>
                    <option value="Google Scholar" ${data && data.link_type === 'Google Scholar' ? 'selected' : ''}>Google Scholar</option>
                    <option value="ORCID" ${data && data.link_type === 'ORCID' ? 'selected' : ''}>ORCID</option>
                    <option value="Academia.edu" ${data && data.link_type === 'Academia.edu' ? 'selected' : ''}>Academia.edu</option>
                    <option value="GitHub" ${data && data.link_type === 'GitHub' ? 'selected' : ''}>GitHub</option>
                    <option value="Twitter" ${data && data.link_type === 'Twitter' ? 'selected' : ''}>Twitter</option>
                    <option value="Other" ${data && data.link_type === 'Other' ? 'selected' : ''}>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Link Label *</label>
                <input type="text" name="link_label" required value="${data ? data.link_label : ''}" 
                       placeholder="e.g., My LinkedIn Profile">
            </div>
            <div class="form-group">
                <label>URL *</label>
                <input type="url" name="link_url" required value="${data ? data.link_url : ''}"
                       placeholder="https://...">
            </div>
            <div class="checkbox-group">
                <input type="checkbox" name="is_visible" id="link_visible" ${!data || data.is_visible ? 'checked' : ''}>
                <label for="link_visible">Visible in CV</label>
            </div>
        </form>
    `;
    
    showModal(data ? 'Edit Web Link' : 'Add Web Link', content, function() {
        const formData = new FormData(document.getElementById('webLinkForm'));
        formData.append('action', 'save_web_link');
        
        fetch('cv_information_save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        });
    });
}

function deleteWebLink(linkId) {
    if (confirm('Are you sure you want to delete this web link?')) {
        fetch('cv_information_save.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete_web_link&id=${linkId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Profile photo upload
function uploadProfilePhoto(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Check file size (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            return;
        }
        
        // Check file type
        if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
            alert('Only JPG and PNG files are allowed');
            return;
        }
        
        // Preview image
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePhotoPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
        
        // Upload file
        const formData = new FormData();
        formData.append('action', 'upload_profile_photo');
        formData.append('profile_photo', file);
        
        fetch('cv_information_save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Profile photo updated successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Upload failed: ' + error.message);
        });
    }
}

console.log('CV Information page loaded');
</script>

<!-- Include external JavaScript functions -->
<script src="../assets/js/cv_information_functions.js"></script>

<?php include 'faculty_footer.php'; ?>