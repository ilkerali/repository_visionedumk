<?php
/**
 * CV Preview Page - Europass Format
 * 
 * Europass formatında CV önizleme sayfası
 * CV preview page in Europass format matching the PDF style
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$user = $stmt->fetch();

// Get complete CV data using stored procedure
try {
    $stmt = $pdo->prepare("CALL GetCompleteCV(:user_id)");
    $stmt->execute([':user_id' => $userId]);
    
    // Profile
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Web Links
    $webLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Work Experience
    $workExperience = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Education
    $education = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Languages
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Personal Skills
    $personalSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Additional Info
    $additionalInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Driving Licenses
    $drivingLicenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Publications
    $publications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("CV Preview Error: " . $e->getMessage());
    $profile = null;
    $webLinks = [];
    $workExperience = [];
    $education = [];
    $languages = [];
    $personalSkills = [];
    $additionalInfo = [];
    $drivingLicenses = [];
    $publications = [];
}

// Group publications by type
$pubsByType = [];
if (!empty($publications)) {
    foreach ($publications as $pub) {
        $type = $pub['type_name_en'] ?? 'Other';
        if (!isset($pubsByType[$type])) {
            $pubsByType[$type] = [];
        }
        $pubsByType[$type][] = $pub;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Vitae - <?php echo sanitize($user['full_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Europass CV Styles */
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #000;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .cv-container {
            max-width: 210mm; /* A4 width */
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .cv-page {
            padding: 20mm;
            background: white;
            min-height: 277mm; /* A4 height minus padding */
        }
        
        /* Europass Header */
        .cv-header {
            border-bottom: 3px solid #003399;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .cv-title {
            font-size: 24pt;
            font-weight: bold;
            color: #003399;
            margin: 0 0 5px 0;
        }
        
        .cv-name {
            font-size: 18pt;
            font-weight: bold;
            color: #000;
            margin: 10px 0;
        }
        
        /* Two Column Layout */
        .cv-section {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        
        .cv-section-left {
            display: table-cell;
            width: 35%;
            vertical-align: top;
            padding-right: 15px;
        }
        
        .cv-section-right {
            display: table-cell;
            width: 65%;
            vertical-align: top;
        }
        
        .cv-section-heading {
            font-size: 11pt;
            font-weight: bold;
            color: #003399;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .cv-section-subheading {
            font-size: 10pt;
            color: #666;
            font-style: italic;
            margin-bottom: 8px;
        }
        
        /* Content Styles */
        .cv-item {
            margin-bottom: 12px;
        }
        
        .cv-item-title {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .cv-item-subtitle {
            color: #666;
            margin-bottom: 3px;
        }
        
        .cv-item-period {
            color: #666;
            font-size: 9pt;
            margin-bottom: 5px;
        }
        
        .cv-item-description {
            margin-top: 5px;
            text-align: justify;
        }
        
        /* Language Skills Table */
        .language-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9pt;
        }
        
        .language-table th,
        .language-table td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            text-align: center;
        }
        
        .language-table th {
            background-color: #e6e6e6;
            font-weight: bold;
        }
        
        .language-table td:first-child {
            text-align: left;
            font-weight: bold;
        }
        
        /* Driving Licenses */
        .driving-badges {
            display: inline-block;
        }
        
        .driving-badge {
            display: inline-block;
            background: #003399;
            color: white;
            padding: 3px 8px;
            margin-right: 5px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 9pt;
        }
        
        /* Publications */
        .publication-item {
            margin-bottom: 8px;
            text-align: justify;
        }
        
        .publication-authors {
            font-weight: normal;
        }
        
        .publication-title {
            font-style: italic;
        }
        
        .publication-details {
            color: #666;
            font-size: 9pt;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .cv-container {
                box-shadow: none;
                max-width: 100%;
            }
            
            .no-print {
                display: none !important;
            }
            
            .cv-page {
                page-break-after: always;
            }
            
            .cv-page:last-child {
                page-break-after: auto;
            }
            
            .cv-section {
                page-break-inside: avoid;
            }
        }
        
        /* Action Buttons */
        .action-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .btn-print {
            background: #003399;
            color: white;
        }
        
        .btn-back {
            background: #666;
            color: white;
        }
        
        .btn-pdf {
            background: #d9534f;
            color: white;
        }
        
        .action-btn:hover {
            opacity: 0.9;
        }
        
        @media screen and (max-width: 768px) {
            .cv-section-left,
            .cv-section-right {
                display: block;
                width: 100%;
            }
            
            .cv-section-left {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>

<!-- Action Buttons -->
<div class="action-bar no-print">
    <button onclick="window.history.back()" class="action-btn btn-back">
        ← Back
    </button>
    <button onclick="window.print()" class="action-btn btn-print">
        🖨️ Print
    </button>
    <a href="cv_export_pdf.php" class="action-btn btn-pdf" style="text-decoration: none;">
        📄 Export PDF
    </a>
</div>

<div class="cv-container">
    <div class="cv-page">
        
        <!-- HEADER -->
        <div class="cv-header">
            <div class="cv-title">CURRICULUM VITAE</div>
            <div class="cv-name"><?php echo strtoupper(sanitize($user['full_name'])); ?></div>
        </div>
        
        <!-- PERSONAL INFORMATION -->
        <div class="cv-section">
            <div class="cv-section-left">
                <div class="cv-section-heading">Personal Information</div>
                
                <!-- Profile Photo -->
                <div style="margin-top: 15px; text-align: center;">
                    <?php 
                    // Check if user has profile image
                    $profileImage = '';
                    if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])) {
                        $profileImage = '../' . $user['profile_image'];
                    } else {
                        // Default placeholder image
                        $profileImage = 'https://via.placeholder.com/150x180/CCCCCC/666666?text=Photo';
                    }
                    ?>
                    <img src="<?php echo $profileImage; ?>" 
                         alt="<?php echo sanitize($user['full_name']); ?>" 
                         style="width: 120px; height: 144px; object-fit: cover; border: 2px solid #003399; border-radius: 4px;">
                </div>
            </div>
            <div class="cv-section-right">
                <?php if ($profile): ?>
                    <?php if ($profile['date_of_birth']): ?>
                        <div><strong>Date of birth:</strong> <?php echo date('d F Y', strtotime($profile['date_of_birth'])); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($profile['place_of_birth']): ?>
                        <div><strong>Place of birth:</strong> <?php echo sanitize($profile['place_of_birth']); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($profile['nationality']): ?>
                        <div><strong>Nationality:</strong> <?php echo sanitize($profile['nationality']); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($profile['gender']): ?>
                        <div><strong>Gender:</strong> <?php echo sanitize($profile['gender']); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div style="margin-top: 10px;">
                    <?php 
                    $phone = $profile['cv_phone'] ?? $user['phone'] ?? '';
                    $email = $profile['cv_email'] ?? $user['email'];
                    $address = $profile['cv_address'] ?? '';
                    $city = $profile['cv_city'] ?? '';
                    $postalCode = $profile['cv_postal_code'] ?? '';
                    $country = $profile['cv_country'] ?? '';
                    ?>
                    
                    <?php if ($phone): ?>
                        <div><strong>Telephone:</strong> <?php echo sanitize($phone); ?></div>
                    <?php endif; ?>
                    
                    <div><strong>E-mail:</strong> <?php echo sanitize($email); ?></div>
                    
                    <?php if ($address): ?>
                        <div style="margin-top: 5px;">
                            <strong>Address:</strong><br>
                            <?php echo sanitize($address); ?><br>
                            <?php if ($postalCode || $city): ?>
                                <?php echo sanitize($postalCode); ?> <?php echo sanitize($city); ?><br>
                            <?php endif; ?>
                            <?php echo sanitize($country); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Web Links -->
                <?php if (!empty($webLinks) || $userId): ?>
                    <div style="margin-top: 10px;">
                        <strong>Web profiles:</strong><br>
                        
                        <!-- Faculty Profile Link -->
                        <div style="margin-top: 3px;">
                            <strong>Faculty Profile:</strong> 
                            <a href="https://repository.vision.edu.mk/faculty_profile.php?id=<?php echo $userId; ?>" target="_blank" style="color: #003399; text-decoration: none;">
                                https://repository.vision.edu.mk/faculty_profile.php?id=<?php echo $userId; ?>
                            </a>
                        </div>
                        
                        <?php foreach ($webLinks as $link): ?>
                            <div style="margin-top: 3px;">
                                <strong><?php echo sanitize($link['link_label']); ?>:</strong> 
                                <a href="<?php echo sanitize($link['link_url']); ?>" target="_blank" style="color: #003399; text-decoration: none;">
                                    <?php echo sanitize($link['link_url']); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- WORK EXPERIENCE -->
        <?php if (!empty($workExperience)): ?>
        <div class="cv-section">
            <div class="cv-section-left">
                <div class="cv-section-heading">Work Experience</div>
            </div>
            <div class="cv-section-right">
                <?php foreach ($workExperience as $work): ?>
                    <div class="cv-item">
                        <div class="cv-item-period">
                            <?php echo date('m/Y', strtotime($work['start_date'])); ?> – 
                            <?php echo $work['is_current'] ? 'Present' : date('m/Y', strtotime($work['end_date'])); ?>
                        </div>
                        <div class="cv-item-title">
                            <?php echo sanitize($work['job_title']); ?>
                        </div>
                        <div class="cv-item-subtitle">
                            <?php echo sanitize($work['employer']); ?>
                            <?php if ($work['location']): ?>
                                (<?php echo sanitize($work['location']); ?>)
                            <?php endif; ?>
                        </div>
                        <?php if ($work['description']): ?>
                            <div class="cv-item-description">
                                <?php echo nl2br(sanitize($work['description'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- EDUCATION AND TRAINING -->
        <?php if (!empty($education)): ?>
        <div class="cv-section">
            <div class="cv-section-left">
                <div class="cv-section-heading">Education and Training</div>
            </div>
            <div class="cv-section-right">
                <?php foreach ($education as $edu): ?>
                    <div class="cv-item">
                        <div class="cv-item-period">
                            <?php echo $edu['graduation_year']; ?>
                            <?php if ($edu['start_date'] && $edu['end_date']): ?>
                                (<?php echo date('m/Y', strtotime($edu['start_date'])); ?> – 
                                <?php echo date('m/Y', strtotime($edu['end_date'])); ?>)
                            <?php endif; ?>
                        </div>
                        <div class="cv-item-title">
                            <?php echo sanitize($edu['degree']); ?>
                            <?php if ($edu['field_of_study']): ?>
                                in <?php echo sanitize($edu['field_of_study']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="cv-item-subtitle">
                            <?php echo sanitize($edu['institution']); ?>
                            <?php if ($edu['location']): ?>
                                (<?php echo sanitize($edu['location']); ?>)
                            <?php endif; ?>
                        </div>
                        <?php if ($edu['thesis_title']): ?>
                            <div style="margin-top: 5px;">
                                <strong>Thesis:</strong> <em><?php echo sanitize($edu['thesis_title']); ?></em>
                                <?php if ($edu['thesis_supervisor']): ?>
                                    <br><strong>Supervisor:</strong> <?php echo sanitize($edu['thesis_supervisor']); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($edu['grade']): ?>
                            <div><strong>Grade:</strong> <?php echo sanitize($edu['grade']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- PERSONAL SKILLS -->
        <?php if (!empty($personalSkills)): ?>
            <?php 
            // Group skills by category
            $skillsByCategory = [];
            foreach ($personalSkills as $skill) {
                $category = $skill['skill_category'];
                if (!isset($skillsByCategory[$category])) {
                    $skillsByCategory[$category] = [];
                }
                $skillsByCategory[$category][] = $skill;
            }
            ?>
            
            <?php foreach ($skillsByCategory as $category => $skills): ?>
            <div class="cv-section">
                <div class="cv-section-left">
                    <div class="cv-section-heading"><?php echo sanitize($category); ?></div>
                    <?php if ($skills[0]['skill_subcategory']): ?>
                        <div class="cv-section-subheading"><?php echo sanitize($skills[0]['skill_subcategory']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="cv-section-right">
                    <?php foreach ($skills as $skill): ?>
                        <div style="margin-bottom: 8px;">
                            <?php echo nl2br(sanitize($skill['skill_description'])); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- LANGUAGE SKILLS -->
        <?php if (!empty($languages)): ?>
        <div class="cv-section">
            <div class="cv-section-left">
                <div class="cv-section-heading">Language Skills</div>
            </div>
            <div class="cv-section-right">
                <?php 
                $motherTongues = array_filter($languages, function($lang) { return $lang['is_mother_tongue']; });
                $otherLanguages = array_filter($languages, function($lang) { return !$lang['is_mother_tongue']; });
                ?>
                
                <?php if (!empty($motherTongues)): ?>
                    <div style="margin-bottom: 10px;">
                        <strong>Mother tongue(s):</strong>
                        <?php 
                        $mtNames = array_map(function($lang) { return sanitize($lang['language']); }, $motherTongues);
                        echo implode(', ', $mtNames);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($otherLanguages)): ?>
                    <div style="margin-bottom: 5px;"><strong>Other language(s):</strong></div>
                    
                    <table class="language-table">
                        <thead>
                            <tr>
                                <th rowspan="2">Language</th>
                                <th colspan="2">Understanding</th>
                                <th colspan="2">Speaking</th>
                                <th rowspan="2">Writing</th>
                            </tr>
                            <tr>
                                <th>Listening</th>
                                <th>Reading</th>
                                <th>Interaction</th>
                                <th>Production</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($otherLanguages as $lang): ?>
                            <tr>
                                <td><?php echo sanitize($lang['language']); ?></td>
                                <td><?php echo $lang['listening_level'] ?: '-'; ?></td>
                                <td><?php echo $lang['reading_level'] ?: '-'; ?></td>
                                <td><?php echo $lang['spoken_interaction_level'] ?: '-'; ?></td>
                                <td><?php echo $lang['spoken_production_level'] ?: '-'; ?></td>
                                <td><?php echo $lang['writing_level'] ?: '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 8px; font-size: 8pt; color: #666;">
                        Levels: A1/A2: Basic user - B1/B2: Independent user - C1/C2: Proficient user<br>
                        Common European Framework of Reference for Languages
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- DRIVING LICENSE -->
        <?php if (!empty($drivingLicenses)): ?>
        <div class="cv-section">
            <div class="cv-section-left">
                <div class="cv-section-heading">Driving License</div>
            </div>
            <div class="cv-section-right">
                <div class="driving-badges">
                    <?php foreach ($drivingLicenses as $license): ?>
                        <span class="driving-badge"><?php echo sanitize($license['license_category']); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- PUBLICATIONS -->
        <?php if (!empty($pubsByType)): ?>
        <div class="cv-section">
            <div class="cv-section-left">
                <div class="cv-section-heading">Publications</div>
            </div>
            <div class="cv-section-right">
                <?php foreach ($pubsByType as $type => $pubs): ?>
                    <div style="margin-bottom: 15px;">
                        <div style="font-weight: bold; margin-bottom: 8px; color: #003399;">
                            <?php echo sanitize($type); ?> (<?php echo count($pubs); ?>)
                        </div>
                        <?php foreach ($pubs as $pub): ?>
                            <div class="publication-item">
                                <span class="publication-authors"><?php echo sanitize($pub['authors']); ?></span>
                                (<?php echo $pub['publication_year']; ?>).
                                <span class="publication-title"><?php echo sanitize($pub['title']); ?>.</span>
                                
                                <?php if ($pub['journal_name']): ?>
                                    <span class="publication-details">
                                        <?php echo sanitize($pub['journal_name']); ?>
                                        <?php if ($pub['volume']): ?>, <?php echo $pub['volume']; ?><?php endif; ?>
                                        <?php if ($pub['issue']): ?>(<?php echo $pub['issue']; ?>)<?php endif; ?>
                                        <?php if ($pub['pages']): ?>, pp. <?php echo $pub['pages']; ?><?php endif; ?>.
                                    </span>
                                <?php elseif ($pub['conference_name']): ?>
                                    <span class="publication-details">
                                        <?php echo sanitize($pub['conference_name']); ?>,
                                        <?php echo sanitize($pub['conference_location']); ?>.
                                    </span>
                                <?php elseif ($pub['publisher']): ?>
                                    <span class="publication-details">
                                        <?php echo sanitize($pub['publisher']); ?>.
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($pub['doi']): ?>
                                    <br><span class="publication-details">DOI: <?php echo sanitize($pub['doi']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ADDITIONAL INFORMATION -->
        <?php if (!empty($additionalInfo)): ?>
            <?php 
            // Group by category
            $infoByCategory = [];
            foreach ($additionalInfo as $info) {
                $category = $info['info_category'];
                if (!isset($infoByCategory[$category])) {
                    $infoByCategory[$category] = [];
                }
                $infoByCategory[$category][] = $info;
            }
            ?>
            
            <?php foreach ($infoByCategory as $category => $infos): ?>
            <div class="cv-section">
                <div class="cv-section-left">
                    <div class="cv-section-heading"><?php echo sanitize($category); ?></div>
                </div>
                <div class="cv-section-right">
                    <?php foreach ($infos as $info): ?>
                        <div class="cv-item">
                            <?php if ($info['info_subcategory']): ?>
                                <div class="cv-item-title"><?php echo sanitize($info['info_subcategory']); ?></div>
                            <?php endif; ?>
                            <?php if ($info['start_date'] || $info['end_date']): ?>
                                <div class="cv-item-period">
                                    <?php if ($info['start_date']): ?>
                                        <?php echo date('Y', strtotime($info['start_date'])); ?>
                                        <?php if ($info['end_date']): ?>
                                            – <?php echo date('Y', strtotime($info['end_date'])); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div><?php echo nl2br(sanitize($info['info_content'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Footer -->
        <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ccc; text-align: center; font-size: 8pt; color: #666;">
            Generated on <?php echo date('d F Y'); ?> | International Vision University Repository System
        </div>
        
    </div>
</div>

</body>
</html>