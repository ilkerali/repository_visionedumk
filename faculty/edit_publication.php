<?php
/**
 * Edit Publication Page (English Interface)
 * * Mevcut bir yayının detaylarını güncellemek için kullanılır.
 * Arayüz İngilizce, yorumlar Türkçe bırakılmıştır.
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');

// Sadece faculty yetkisi olanlar girebilir
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../dashboard.php");
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// URL'den ID'yi al
$pubId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ID geçerli mi ve yayın bu kullanıcıya mı ait kontrol et
if ($pubId > 0) {
    try {
        $stmtCheck = $pdo->prepare("SELECT * FROM publications WHERE publication_id = :id AND user_id = :user_id");
        $stmtCheck->execute([':id' => $pubId, ':user_id' => $userId]);
        $publication = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$publication) {
            // Yayın bulunamadı veya kullanıcıya ait değil
            header("Location: my_publications.php?error=notfound");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Edit check error: " . $e->getMessage());
        die("An error occurred.");
    }
} else {
    header("Location: my_publications.php");
    exit();
}

// Yayın türlerini al (Select kutusu için)
try {
    $stmtTypes = $pdo->query("SELECT * FROM publication_types WHERE is_active = TRUE ORDER BY display_order ASC");
    $publicationTypes = $stmtTypes->fetchAll();
} catch (PDOException $e) {
    $publicationTypes = [];
}

// Form Gönderildiğinde (GÜNCELLEME İŞLEMİ)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF token kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form request. Please refresh the page and try again.';
    } else {
        
        // Form verilerini al ve temizle
        $typeId = intval($_POST['type_id'] ?? 0);
        $title = cleanInput($_POST['title'] ?? '');
        $authors = cleanInput($_POST['authors'] ?? '');
        $publicationYear = intval($_POST['publication_year'] ?? 0);
        $publicationDate = !empty($_POST['publication_date']) ? cleanInput($_POST['publication_date']) : null;
        $abstract = cleanInput($_POST['abstract'] ?? '');
        $keywords = cleanInput($_POST['keywords'] ?? '');
        $language = cleanInput($_POST['language'] ?? 'en'); // Varsayılan 'en' yaptım
        
        // Opsiyonel alanlar
        $journalName = cleanInput($_POST['journal_name'] ?? '');
        $volume = cleanInput($_POST['volume'] ?? '');
        $issue = cleanInput($_POST['issue'] ?? '');
        $pages = cleanInput($_POST['pages'] ?? '');
        $issn = cleanInput($_POST['issn'] ?? '');
        $journalQuartile = !empty($_POST['journal_quartile']) ? cleanInput($_POST['journal_quartile']) : null;
        $impactFactor = !empty($_POST['impact_factor']) ? floatval($_POST['impact_factor']) : null;
        
        $conferenceName = cleanInput($_POST['conference_name'] ?? '');
        $conferenceLocation = cleanInput($_POST['conference_location'] ?? '');
        $conferenceStartDate = !empty($_POST['conference_start_date']) ? cleanInput($_POST['conference_start_date']) : null;
        $conferenceEndDate = !empty($_POST['conference_end_date']) ? cleanInput($_POST['conference_end_date']) : null;
        $presentationType = !empty($_POST['presentation_type']) ? cleanInput($_POST['presentation_type']) : null;
        $proceedingsPublished = isset($_POST['proceedings_published']) ? 1 : 0;
        
        $publisher = cleanInput($_POST['publisher'] ?? '');
        $isbn = cleanInput($_POST['isbn'] ?? '');
        $edition = cleanInput($_POST['edition'] ?? '');
        $totalPages = !empty($_POST['total_pages']) ? intval($_POST['total_pages']) : null;
        $bookType = !empty($_POST['book_type']) ? cleanInput($_POST['book_type']) : null;
        $chapterTitle = cleanInput($_POST['chapter_title'] ?? '');
        $chapterPages = cleanInput($_POST['chapter_pages'] ?? '');
        
        $projectRole = cleanInput($_POST['project_role'] ?? '');
        $fundingAgency = cleanInput($_POST['funding_agency'] ?? '');
        $grantAmount = !empty($_POST['grant_amount']) ? floatval($_POST['grant_amount']) : null;
        $projectStartDate = !empty($_POST['project_start_date']) ? cleanInput($_POST['project_start_date']) : null;
        $projectEndDate = !empty($_POST['project_end_date']) ? cleanInput($_POST['project_end_date']) : null;
        $projectStatus = !empty($_POST['project_status']) ? cleanInput($_POST['project_status']) : null;
        
        $doi = cleanInput($_POST['doi'] ?? '');
        $url = cleanInput($_POST['url'] ?? '');
        $citationCount = !empty($_POST['citation_count']) ? intval($_POST['citation_count']) : 0;
        $accessType = !empty($_POST['access_type']) ? cleanInput($_POST['access_type']) : null;
        $status = cleanInput($_POST['status'] ?? 'published');
        $visibility = cleanInput($_POST['visibility'] ?? 'public');
        $notes = cleanInput($_POST['notes'] ?? '');
        
        // Validasyon
        if (empty($typeId) || empty($title) || empty($authors) || empty($publicationYear)) {
            $error = 'Publication type, title, authors, and year are required fields.';
        } else {
            try {
                // GÜNCELLEME SORGUSU
                $sql = "UPDATE publications SET 
                        type_id = :type_id, title = :title, authors = :authors, 
                        publication_year = :publication_year, publication_date = :publication_date,
                        abstract = :abstract, keywords = :keywords, language = :language,
                        journal_name = :journal_name, volume = :volume, issue = :issue, pages = :pages, 
                        issn = :issn, journal_quartile = :journal_quartile, impact_factor = :impact_factor,
                        conference_name = :conference_name, conference_location = :conference_location, 
                        conference_start_date = :conference_start_date, conference_end_date = :conference_end_date,
                        presentation_type = :presentation_type, proceedings_published = :proceedings_published,
                        publisher = :publisher, isbn = :isbn, edition = :edition, total_pages = :total_pages, 
                        book_type = :book_type, chapter_title = :chapter_title, chapter_pages = :chapter_pages,
                        project_role = :project_role, funding_agency = :funding_agency, grant_amount = :grant_amount, 
                        project_start_date = :project_start_date, project_end_date = :project_end_date, project_status = :project_status,
                        doi = :doi, url = :url, citation_count = :citation_count, 
                        access_type = :access_type, status = :status, visibility = :visibility, notes = :notes,
                        updated_at = NOW()
                        WHERE publication_id = :id AND user_id = :user_id";

                $stmt = $pdo->prepare($sql);
                
                $stmt->execute([
                    ':type_id' => $typeId, ':title' => $title, ':authors' => $authors,
                    ':publication_year' => $publicationYear, ':publication_date' => $publicationDate,
                    ':abstract' => $abstract, ':keywords' => $keywords, ':language' => $language,
                    ':journal_name' => $journalName, ':volume' => $volume, ':issue' => $issue, 
                    ':pages' => $pages, ':issn' => $issn, ':journal_quartile' => $journalQuartile, ':impact_factor' => $impactFactor,
                    ':conference_name' => $conferenceName, ':conference_location' => $conferenceLocation, 
                    ':conference_start_date' => $conferenceStartDate, ':conference_end_date' => $conferenceEndDate,
                    ':presentation_type' => $presentationType, ':proceedings_published' => $proceedingsPublished,
                    ':publisher' => $publisher, ':isbn' => $isbn, ':edition' => $edition, 
                    ':total_pages' => $totalPages, ':book_type' => $bookType, ':chapter_title' => $chapterTitle, ':chapter_pages' => $chapterPages,
                    ':project_role' => $projectRole, ':funding_agency' => $fundingAgency, ':grant_amount' => $grantAmount, 
                    ':project_start_date' => $projectStartDate, ':project_end_date' => $projectEndDate, ':project_status' => $projectStatus,
                    ':doi' => $doi, ':url' => $url, ':citation_count' => $citationCount, 
                    ':access_type' => $accessType, ':status' => $status, ':visibility' => $visibility, ':notes' => $notes,
                    ':id' => $pubId, ':user_id' => $userId
                ]);
                
                // Başarılı log ve yönlendirme
                logActivity($pdo, $userId, 'publication_updated', 'publications', $pubId);
                header("Location: my_publications.php?success=updated");
                exit();

            } catch (PDOException $e) {
                error_log("Update publication error: " . $e->getMessage());
                $error = 'An error occurred during the update: ' . $e->getMessage();
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Publication - Academic Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
</head>
<body>
    <?php include '../includes/navbar_faculty.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <div>
                    <h1>Edit Publication</h1>
                    <p class="text-muted">Update existing publication details.</p>
                </div>
                <a href="my_publications.php" class="btn btn-secondary">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Go Back
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?php echo sanitize($error); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="edit_publication.php?id=<?php echo $pubId; ?>" id="publicationForm" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="form-section">
                            <h3 class="form-section-title">Basic Information</h3>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="type_id">Publication Type <span class="required">*</span></label>
                                    <select name="type_id" id="type_id" required>
                                        <option value="">Select...</option>
                                        <?php foreach ($publicationTypes as $type): ?>
                                            <option value="<?php echo $type['type_id']; ?>" 
                                                    data-code="<?php echo $type['type_code']; ?>"
                                                    <?php echo ($publication['type_id'] == $type['type_id']) ? 'selected' : ''; ?>>
                                                <?php echo sanitize($type['type_name_en'] ?? $type['type_name_tr']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="language">Language <span class="required">*</span></label>
                                    <select name="language" id="language" required>
                                        <?php 
                                        $langs = ['en' => 'English', 'tr' => 'Turkish', 'de' => 'German', 'fr' => 'French', 'es' => 'Spanish', 'other' => 'Other'];
                                        foreach($langs as $code => $name) {
                                            $selected = ($publication['language'] == $code) ? 'selected' : '';
                                            echo "<option value='$code' $selected>$name</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="title">Title <span class="required">*</span></label>
                                <input type="text" name="title" id="title" required value="<?php echo sanitize($publication['title']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="authors">Authors <span class="required">*</span></label>
                                <input type="text" name="authors" id="authors" required value="<?php echo sanitize($publication['authors']); ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="publication_year">Year <span class="required">*</span></label>
                                    <input type="number" name="publication_year" id="publication_year" min="1900" max="<?php echo date('Y') + 1; ?>" value="<?php echo sanitize($publication['publication_year']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="publication_date">Full Date (Optional)</label>
                                    <input type="date" name="publication_date" id="publication_date" value="<?php echo sanitize($publication['publication_date']); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="abstract">Abstract</label>
                                <textarea name="abstract" id="abstract" rows="5"><?php echo sanitize($publication['abstract']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="keywords">Keywords</label>
                                <input type="text" name="keywords" id="keywords" value="<?php echo sanitize($publication['keywords']); ?>" placeholder="e.g. machine learning, ai, data mining">
                            </div>
                        </div>

                        <div class="form-section" id="section_article" style="display:none;">
                            <h3 class="form-section-title">Journal Details</h3>
                            <div class="form-group">
                                <label for="journal_name">Journal Name</label>
                                <input type="text" name="journal_name" id="journal_name" value="<?php echo sanitize($publication['journal_name']); ?>">
                            </div>
                            <div class="form-row form-row-3">
                                <div class="form-group"><label for="volume">Volume</label><input type="text" name="volume" id="volume" value="<?php echo sanitize($publication['volume']); ?>"></div>
                                <div class="form-group"><label for="issue">Issue</label><input type="text" name="issue" id="issue" value="<?php echo sanitize($publication['issue']); ?>"></div>
                                <div class="form-group"><label for="pages">Pages</label><input type="text" name="pages" id="pages" value="<?php echo sanitize($publication['pages']); ?>"></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label for="issn">ISSN</label><input type="text" name="issn" id="issn" value="<?php echo sanitize($publication['issn']); ?>"></div>
                                <div class="form-group">
                                    <label for="journal_quartile">Quartile</label>
                                    <select name="journal_quartile" id="journal_quartile">
                                        <option value="">Select...</option>
                                        <?php foreach(['Q1','Q2','Q3','Q4'] as $q) echo '<option value="'.$q.'" '.($publication['journal_quartile']==$q?'selected':'').'>'.$q.'</option>'; ?>
                                    </select>
                                </div>
                                <div class="form-group"><label for="impact_factor">Impact Factor</label><input type="number" name="impact_factor" id="impact_factor" step="0.001" value="<?php echo sanitize($publication['impact_factor']); ?>"></div>
                            </div>
                        </div>

                        <div class="form-section" id="section_conference" style="display:none;">
                            <h3 class="form-section-title">Conference Details</h3>
                            <div class="form-group"><label for="conference_name">Conference Name</label><input type="text" name="conference_name" id="conference_name" value="<?php echo sanitize($publication['conference_name']); ?>"></div>
                            <div class="form-group"><label for="conference_location">Location</label><input type="text" name="conference_location" id="conference_location" value="<?php echo sanitize($publication['conference_location']); ?>"></div>
                            <div class="form-row">
                                <div class="form-group"><label for="conference_start_date">Start Date</label><input type="date" name="conference_start_date" id="conference_start_date" value="<?php echo sanitize($publication['conference_start_date']); ?>"></div>
                                <div class="form-group"><label for="conference_end_date">End Date</label><input type="date" name="conference_end_date" id="conference_end_date" value="<?php echo sanitize($publication['conference_end_date']); ?>"></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="presentation_type">Presentation Type</label>
                                    <select name="presentation_type" id="presentation_type">
                                        <option value="">Select...</option>
                                        <?php foreach(['oral'=>'Oral Presentation','poster'=>'Poster','keynote'=>'Keynote','workshop'=>'Workshop'] as $k=>$v) echo '<option value="'.$k.'" '.($publication['presentation_type']==$k?'selected':'').'>'.$v.'</option>'; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="proceedings_published" value="1" <?php echo ($publication['proceedings_published'] ? 'checked' : ''); ?>>
                                        <span>Proceedings Published</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="section_book" style="display:none;">
                            <h3 class="form-section-title">Book Details</h3>
                            <div class="form-group"><label for="publisher">Publisher</label><input type="text" name="publisher" id="publisher" value="<?php echo sanitize($publication['publisher']); ?>"></div>
                            <div class="form-row form-row-3">
                                <div class="form-group"><label for="isbn">ISBN</label><input type="text" name="isbn" id="isbn" value="<?php echo sanitize($publication['isbn']); ?>"></div>
                                <div class="form-group"><label for="edition">Edition</label><input type="text" name="edition" id="edition" value="<?php echo sanitize($publication['edition']); ?>"></div>
                                <div class="form-group"><label for="total_pages">Total Pages</label><input type="number" name="total_pages" id="total_pages" value="<?php echo sanitize($publication['total_pages']); ?>"></div>
                            </div>
                            <div class="form-group">
                                <label for="book_type">Book Type</label>
                                <select name="book_type" id="book_type">
                                    <option value="">Select...</option>
                                    <?php foreach(['authored'=>'Authored','edited'=>'Edited','chapter'=>'Chapter'] as $k=>$v) echo '<option value="'.$k.'" '.($publication['book_type']==$k?'selected':'').'>'.$v.'</option>'; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-section" id="section_book_chapter" style="display:none;">
                            <h3 class="form-section-title">Book Chapter</h3>
                            <div class="form-group"><label for="chapter_title">Chapter Title</label><input type="text" name="chapter_title" id="chapter_title" value="<?php echo sanitize($publication['chapter_title']); ?>"></div>
                            <div class="form-group"><label for="publisher_chapter">Publisher</label><input type="text" name="publisher" id="publisher_chapter" value="<?php echo sanitize($publication['publisher']); ?>"></div>
                            <div class="form-row">
                                <div class="form-group"><label for="isbn_chapter">ISBN</label><input type="text" name="isbn" id="isbn_chapter" value="<?php echo sanitize($publication['isbn']); ?>"></div>
                                <div class="form-group"><label for="chapter_pages">Pages (Range)</label><input type="text" name="chapter_pages" id="chapter_pages" value="<?php echo sanitize($publication['chapter_pages']); ?>"></div>
                            </div>
                        </div>

                        <div class="form-section" id="section_project" style="display:none;">
                            <h3 class="form-section-title">Project Details</h3>
                            <div class="form-row">
                                <div class="form-group"><label for="project_role">Role</label><input type="text" name="project_role" id="project_role" value="<?php echo sanitize($publication['project_role']); ?>"></div>
                                <div class="form-group">
                                    <label for="project_status">Status</label>
                                    <select name="project_status" id="project_status">
                                        <option value="">Select...</option>
                                        <?php foreach(['ongoing'=>'Ongoing','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$v) echo '<option value="'.$k.'" '.($publication['project_status']==$k?'selected':'').'>'.$v.'</option>'; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group"><label for="funding_agency">Funding Agency</label><input type="text" name="funding_agency" id="funding_agency" value="<?php echo sanitize($publication['funding_agency']); ?>"></div>
                            <div class="form-group"><label for="grant_amount">Budget (Grant Amount)</label><input type="number" name="grant_amount" id="grant_amount" step="0.01" value="<?php echo sanitize($publication['grant_amount']); ?>"></div>
                            <div class="form-row">
                                <div class="form-group"><label for="project_start_date">Start Date</label><input type="date" name="project_start_date" id="project_start_date" value="<?php echo sanitize($publication['project_start_date']); ?>"></div>
                                <div class="form-group"><label for="project_end_date">End Date</label><input type="date" name="project_end_date" id="project_end_date" value="<?php echo sanitize($publication['project_end_date']); ?>"></div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="form-section-title">Additional Info</h3>
                            <div class="form-row">
                                <div class="form-group"><label for="doi">DOI</label><input type="text" name="doi" id="doi" value="<?php echo sanitize($publication['doi']); ?>"></div>
                                <div class="form-group"><label for="url">URL</label><input type="url" name="url" id="url" value="<?php echo sanitize($publication['url']); ?>"></div>
                            </div>
                            <div class="form-row form-row-3">
                                <div class="form-group"><label for="citation_count">Citations</label><input type="number" name="citation_count" id="citation_count" value="<?php echo sanitize($publication['citation_count']); ?>"></div>
                                <div class="form-group">
                                    <label for="access_type">Access Type</label>
                                    <select name="access_type" id="access_type">
                                        <option value="">Select...</option>
                                        <?php foreach(['open'=>'Open Access','restricted'=>'Restricted','subscription'=>'Subscription'] as $k=>$v) echo '<option value="'.$k.'" '.($publication['access_type']==$k?'selected':'').'>'.$v.'</option>'; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="status">Publication Status</label>
                                    <select name="status" id="status">
                                        <?php foreach(['published'=>'Published','under_review'=>'Under Review','draft'=>'Draft','rejected'=>'Rejected'] as $k=>$v) echo '<option value="'.$k.'" '.($publication['status']==$k?'selected':'').'>'.$v.'</option>'; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group"><label for="notes">Notes</label><textarea name="notes" id="notes" rows="3"><?php echo sanitize($publication['notes']); ?></textarea></div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">Update</button>
                            <a href="my_publications.php" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/publication-form.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('type_id');
            if (typeSelect && typeSelect.value) {
                // publication-form.js içindeki change eventini manuel tetikle
                const event = new Event('change');
                typeSelect.dispatchEvent(event);
            }
        });
    </script>
</body>
</html>