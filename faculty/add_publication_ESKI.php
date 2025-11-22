
<?php
/**
 * Yayın Ekleme Formu
 * 
 * Kullanıcıların yeni yayın ekleyebileceği dinamik form sayfası.
 * Form, seçilen yayın türüne göre ilgili alanları gösterir/gizler.
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

// Yayın türlerini al
try {
    $stmtTypes = $pdo->query("
        SELECT * FROM publication_types 
        WHERE is_active = TRUE 
        ORDER BY display_order ASC
    ");
    $publicationTypes = $stmtTypes->fetchAll();
} catch (PDOException $e) {
    error_log("Get publication types error: " . $e->getMessage());
    $publicationTypes = [];
}

// URL'den gelen varsayılan tür
$defaultType = isset($_GET['type']) ? cleanInput($_GET['type']) : '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF token kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Geçersiz form isteği. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        
        // Zorunlu alanları al
        $typeId = intval($_POST['type_id'] ?? 0);
        $title = cleanInput($_POST['title'] ?? '');
        $authors = cleanInput($_POST['authors'] ?? '');
        $publicationYear = intval($_POST['publication_year'] ?? 0);
        $publicationDate = !empty($_POST['publication_date']) ? cleanInput($_POST['publication_date']) : null;
        $abstract = cleanInput($_POST['abstract'] ?? '');
        $keywords = cleanInput($_POST['keywords'] ?? '');
        $language = cleanInput($_POST['language'] ?? 'tr');
        
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
            $error = 'Tür, başlık, yazarlar ve yayın yılı zorunlu alanlardır.';
        } else if ($publicationYear < 1900 || $publicationYear > date('Y') + 1) {
            $error = 'Geçerli bir yayın yılı giriniz.';
        } else {
            
            try {
                // Veritabanına kaydet
                $stmt = $pdo->prepare("
                    INSERT INTO publications (
                        user_id, type_id, title, authors, publication_year, publication_date,
                        abstract, keywords, language,
                        journal_name, volume, issue, pages, issn, journal_quartile, impact_factor,
                        conference_name, conference_location, conference_start_date, conference_end_date,
                        presentation_type, proceedings_published,
                        publisher, isbn, edition, total_pages, book_type, chapter_title, chapter_pages,
                        project_role, funding_agency, grant_amount, project_start_date, project_end_date, project_status,
                        doi, url, citation_count, access_type, status, visibility, notes
                    ) VALUES (
                        :user_id, :type_id, :title, :authors, :publication_year, :publication_date,
                        :abstract, :keywords, :language,
                        :journal_name, :volume, :issue, :pages, :issn, :journal_quartile, :impact_factor,
                        :conference_name, :conference_location, :conference_start_date, :conference_end_date,
                        :presentation_type, :proceedings_published,
                        :publisher, :isbn, :edition, :total_pages, :book_type, :chapter_title, :chapter_pages,
                        :project_role, :funding_agency, :grant_amount, :project_start_date, :project_end_date, :project_status,
                        :doi, :url, :citation_count, :access_type, :status, :visibility, :notes
                    )
                ");
                
                $stmt->execute([
                    ':user_id' => $userId,
                    ':type_id' => $typeId,
                    ':title' => $title,
                    ':authors' => $authors,
                    ':publication_year' => $publicationYear,
                    ':publication_date' => $publicationDate,
                    ':abstract' => $abstract,
                    ':keywords' => $keywords,
                    ':language' => $language,
                    ':journal_name' => $journalName,
                    ':volume' => $volume,
                    ':issue' => $issue,
                    ':pages' => $pages,
                    ':issn' => $issn,
                    ':journal_quartile' => $journalQuartile,
                    ':impact_factor' => $impactFactor,
                    ':conference_name' => $conferenceName,
                    ':conference_location' => $conferenceLocation,
                    ':conference_start_date' => $conferenceStartDate,
                    ':conference_end_date' => $conferenceEndDate,
                    ':presentation_type' => $presentationType,
                    ':proceedings_published' => $proceedingsPublished,
                    ':publisher' => $publisher,
                    ':isbn' => $isbn,
                    ':edition' => $edition,
                    ':total_pages' => $totalPages,
                    ':book_type' => $bookType,
                    ':chapter_title' => $chapterTitle,
                    ':chapter_pages' => $chapterPages,
                    ':project_role' => $projectRole,
                    ':funding_agency' => $fundingAgency,
                    ':grant_amount' => $grantAmount,
                    ':project_start_date' => $projectStartDate,
                    ':project_end_date' => $projectEndDate,
                    ':project_status' => $projectStatus,
                    ':doi' => $doi,
                    ':url' => $url,
                    ':citation_count' => $citationCount,
                    ':access_type' => $accessType,
                    ':status' => $status,
                    ':visibility' => $visibility,
                    ':notes' => $notes
                ]);
                
                $publicationId = $pdo->lastInsertId();
                
                // Aktivite logla
                logActivity($pdo, $userId, 'publication_added', 'publications', $publicationId);
                
                $success = 'Yayın başarıyla eklendi!';
                
                // Formu temizle (redirect ile)
                header("Location: my_publications.php?success=added");
                exit();
                
            } catch (PDOException $e) {
                error_log("Add publication error: " . $e->getMessage());
                $error = 'Yayın eklenirken bir hata oluştu. Lütfen tekrar deneyin.';
            }
        }
    }
}

// CSRF token oluştur
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Yayın Ekle - Akademik Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
</head>
<body>
    <!-- Navbar -->
    <?php include '../includes/navbar_faculty.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <div>
                    <h1>Yeni Yayın Ekle</h1>
                    <p class="text-muted">Akademik yayınınızı sisteme ekleyin.</p>
                </div>
                <a href="my_publications.php" class="btn btn-secondary">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Geri Dön
                </a>
            </div>

            <!-- Hata ve Başarı Mesajları -->
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

            <!-- Form Card -->
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="add_publication.php" id="publicationForm" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <!-- BÖLÜM 1: Yayın Türü ve Temel Bilgiler -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                Temel Bilgiler
                            </h3>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="type_id">
                                        Yayın Türü <span class="required">*</span>
                                    </label>
                                    <select name="type_id" id="type_id" required>
                                        <option value="">Yayın türü seçiniz...</option>
                                        <?php foreach ($publicationTypes as $type): ?>
                                            <option value="<?php echo $type['type_id']; ?>" 
                                                    data-code="<?php echo $type['type_code']; ?>"
                                                    <?php echo ($defaultType === $type['type_code']) ? 'selected' : ''; ?>>
                                                <?php echo sanitize($type['type_name_tr']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-help">Eklemek istediğiniz yayın türünü seçin</small>
                                </div>

                                <div class="form-group">
                                    <label for="language">
                                        Dil <span class="required">*</span>
                                    </label>
                                    <select name="language" id="language" required>
                                        <option value="tr">Türkçe</option>
                                        <option value="en">İngilizce</option>
                                        <option value="de">Almanca</option>
                                        <option value="fr">Fransızca</option>
                                        <option value="es">İspanyolca</option>
                                        <option value="other">Diğer</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="title">
                                    Başlık <span class="required">*</span>
                                </label>
                                <input type="text" name="title" id="title" required 
                                       placeholder="Yayının tam başlığını girin">
                                <small class="form-help">Yayının orijinal dilindeki tam başlığı</small>
                            </div>

                            <div class="form-group">
                                <label for="authors">
                                    Yazarlar <span class="required">*</span>
                                </label>
                                <input type="text" name="authors" id="authors" required 
                                       placeholder="Örn: Ahmet Yılmaz, Ayşe Demir, John Smith">
                                <small class="form-help">Yazarları virgülle ayırarak girin</small>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="publication_year">
                                        Yayın Yılı <span class="required">*</span>
                                    </label>
                                    <input type="number" name="publication_year" id="publication_year" 
                                           min="1900" max="<?php echo date('Y') + 1; ?>" 
                                           value="<?php echo date('Y'); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="publication_date">
                                        Tam Yayın Tarihi
                                    </label>
                                    <input type="date" name="publication_date" id="publication_date">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="abstract">
                                    Özet
                                </label>
                                <textarea name="abstract" id="abstract" rows="5" 
                                          placeholder="Yayının özetini girin..."></textarea>
                                <small class="form-help">Yayının kısa özeti (isteğe bağlı)</small>
                            </div>

                            <div class="form-group">
                                <label for="keywords">
                                    Anahtar Kelimeler
                                </label>
                                <input type="text" name="keywords" id="keywords" 
                                       placeholder="Örn: makine öğrenmesi, yapay zeka, veri madenciliği">
                                <small class="form-help">Anahtar kelimeleri virgülle ayırarak girin</small>
                            </div>
                        </div>

                        <!-- BÖLÜM 2: Makale Özel Alanlar -->
                        <div class="form-section" id="section_article" style="display:none;">
                            <h3 class="form-section-title">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                Dergi Bilgileri
                            </h3>

                            <div class="form-group">
                                <label for="journal_name">
                                    Dergi Adı <span class="required">*</span>
                                </label>
                                <input type="text" name="journal_name" id="journal_name" 
                                       placeholder="Derginin tam adını girin">
                            </div>

                            <div class="form-row form-row-3">
                                <div class="form-group">
                                    <label for="volume">Cilt</label>
                                    <input type="text" name="volume" id="volume" placeholder="Örn: 15">
                                </div>

                                <div class="form-group">
                                    <label for="issue">Sayı</label>
                                    <input type="text" name="issue" id="issue" placeholder="Örn: 3">
                                </div>

                                <div class="form-group">
                                    <label for="pages">Sayfa Aralığı</label>
                                    <input type="text" name="pages" id="pages" placeholder="Örn: 45-67">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="issn">ISSN</label>
                                    <input type="text" name="issn" id="issn" placeholder="Örn: 1234-5678">
                                </div>

                                <div class="form-group">
                                    <label for="journal_quartile">Dergi Çeyreği (Quartile)</label>
                                    <select name="journal_quartile" id="journal_quartile">
                                        <option value="">Seçiniz...</option>
                                        <option value="Q1">Q1</option>
                                        <option value="Q2">Q2</option>
                                        <option value="Q3">Q3</option>
                                        <option value="Q4">Q4</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="impact_factor">Etki Faktörü (Impact Factor)</label>
                                    <input type="number" name="impact_factor" id="impact_factor" 
                                           step="0.001" placeholder="Örn: 2.456">
                                </div>
                            </div>
                        </div>

                        <!-- BÖLÜM 3: Konferans Özel Alanlar -->
                        <div class="form-section" id="section_conference" style="display:none;">
                            <h3 class="form-section-title">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                </svg>
                                Konferans Bilgileri
                            </h3>

                            <div class="form-group">
                                <label for="conference_name">
                                    Konferans Adı <span class="required">*</span>
                                </label>
                                <input type="text" name="conference_name" id="conference_name" 
                                       placeholder="Konferansın tam adını girin">
                            </div>

                            <div class="form-group">
                                <label for="conference_location">
                                    Konferans Yeri
                                </label>
                                <input type="text" name="conference_location" id="conference_location" 
                                       placeholder="Örn: İstanbul, Türkiye">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="conference_start_date">Başlangıç Tarihi</label>
                                    <input type="date" name="conference_start_date" id="conference_start_date">
                                </div>

                                <div class="form-group">
                                    <label for="conference_end_date">Bitiş Tarihi</label>
                                    <input type="date" name="conference_end_date" id="conference_end_date">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="presentation_type">Sunum Türü</label>
                                    <select name="presentation_type" id="presentation_type">
                                        <option value="">Seçiniz...</option>
                                        <option value="oral">Sözlü Sunum (Oral)</option>
                                        <option value="poster">Poster</option>
                                        <option value="keynote">Davetli Konuşma (Keynote)</option>
                                        <option value="workshop">Workshop</option>
                                        <option value="panel">Panel</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="proceedings_published" value="1">
                                        <span>Bildiri kitabı yayınlandı</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- BÖLÜM 4: Kitap Özel Alanlar -->
                        <div class="form-section" id="section_book" style="display:none;">
                            <h3 class="form-section-title">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                                Kitap Bilgileri
                            </h3>

                            <div class="form-group">
                                <label for="publisher">
                                    Yayınevi <span class="required">*</span>
                                </label>
                                <input type="text" name="publisher" id="publisher" 
                                       placeholder="Yayınevi adını girin">
                            </div>

                            <div class="form-row form-row-3">
                                <div class="form-group">
                                    <label for="isbn">ISBN</label>
                                    <input type="text" name="isbn" id="isbn" placeholder="Örn: 978-3-16-148410-0">
                                </div>

                                <div class="form-group">
                                    <label for="edition">Baskı</label>
                                    <input type="text" name="edition" id="edition" placeholder="Örn: 1. Baskı">
                                </div>

                                <div class="form-group">
                                    <label for="total_pages">Toplam Sayfa</label>
                                    <input type="number" name="total_pages" id="total_pages" placeholder="Örn: 350">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="book_type">Kitap Türü</label>
                                <select name="book_type" id="book_type">
                                    <option value="">Seçiniz...</option>
                                    <option value="authored">Telif (Authored)</option>
                                    <option value="edited">Editörlük (Edited)</option>
                                    <option value="chapter">Bölüm (Chapter)</option>
                                </select>
                            </div>
                        </div>

                        <!-- BÖLÜM 5: Kitap Bölümü Özel Alanlar -->
                        <div class="form-section" id="section_book_chapter" style="display:none;">
                            <h3 class="form-section-title">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                                Kitap Bölümü Bilgileri
                            </h3>

                            <div class="form-group">
                                <label for="chapter_title">
                                    Bölüm Başlığı <span class="required">*</span>
                                </label>
                                <input type="text" name="chapter_title" id="chapter_title" 
                                       placeholder="Kitap bölümünün başlığını girin">
                            </div>

                            <div class="form-group">
                                <label for="publisher">
                                    Yayınevi <span class="required">*</span>
                                </label>
                                <input type="text" name="publisher" id="publisher_chapter" 
                                       placeholder="Yayınevi adını girin">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="isbn">ISBN</label>
                                    <input type="text" name="isbn" id="isbn_chapter" placeholder="Örn: 978-3-16-148410-0">
                                </div>

                                <div class="form-group">
                                    <label for="chapter_pages">Bölüm Sayfa Aralığı</label>
                                    <input type="text" name="chapter_pages" id="chapter_pages" placeholder="Örn: 45-78">
                                </div>
                            </div>
                        </div>

                        <!-- BÖLÜM 6: Proje Özel Alanlar -->
                        <div class="form-section" id="section_project" style="display:none;">
                            <h3 class="form-section-title">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                    <polyline points="2 17 12 22 22 17"></polyline>
                                    <polyline points="2 12 12 17 22 12"></polyline>
                                </svg>
                                Proje Bilgileri
                            </h3>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="project_role">
                                        Projede Rolünüz <span class="required">*</span>
                                    </label>
                                    <input type="text" name="project_role" id="project_role" 
                                           placeholder="Örn: Proje Yürütücüsü, Araştırmacı">
                                </div>

                                <div class="form-group">
                                    <label for="project_status">Proje Durumu</label>
                                    <select name="project_status" id="project_status">
                                        <option value="">Seçiniz...</option>
<option value="ongoing">Devam Ediyor</option>
                                        <option value="completed">Tamamlandı</option>
                                        <option value="cancelled">İptal Edildi</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="funding_agency">
                                    Fonlayan Kurum
                                </label>
                                <input type="text" name="funding_agency" id="funding_agency" 
                                       placeholder="Örn: TÜBİTAK, AB Horizon 2020">
                            </div>

                            <div class="form-group">
                                <label for="grant_amount">
                                    Proje Bütçesi (₺)
                                </label>
                                <input type="number" name="grant_amount" id="grant_amount" 
                                       step="0.01" placeholder="Örn: 150000.00">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="project_start_date">Başlangıç Tarihi</label>
                                    <input type="date" name="project_start_date" id="project_start_date">
                                </div>

                                <div class="form-group">
                                    <label for="project_end_date">Bitiş Tarihi</label>
                                    <input type="date" name="project_end_date" id="project_end_date">
                                </div>
                            </div>
                        </div>

                        <!-- BÖLÜM 7: Ortak Ek Bilgiler -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                </svg>
                                Ek Bilgiler
                            </h3>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="doi">DOI</label>
                                    <input type="text" name="doi" id="doi" 
                                           placeholder="Örn: 10.1000/xyz123">
                                    <small class="form-help">Digital Object Identifier</small>
                                </div>

                                <div class="form-group">
                                    <label for="url">URL / Link</label>
                                    <input type="url" name="url" id="url" 
                                           placeholder="https://example.com/publication">
                                </div>
                            </div>

                            <div class="form-row form-row-3">
                                <div class="form-group">
                                    <label for="citation_count">Atıf Sayısı</label>
                                    <input type="number" name="citation_count" id="citation_count" 
                                           value="0" min="0">
                                </div>

                                <div class="form-group">
                                    <label for="access_type">Erişim Türü</label>
                                    <select name="access_type" id="access_type">
                                        <option value="">Seçiniz...</option>
                                        <option value="open">Açık Erişim</option>
                                        <option value="restricted">Kısıtlı Erişim</option>
                                        <option value="subscription">Abonelik Gerekli</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="status">Durum</label>
                                    <select name="status" id="status">
                                        <option value="published">Yayınlandı</option>
                                        <option value="under_review">İnceleme Aşamasında</option>
                                        <option value="draft">Taslak</option>
                                        <option value="rejected">Reddedildi</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="visibility">Görünürlük</label>
                                <select name="visibility" id="visibility">
                                    <option value="public">Herkese Açık</option>
                                    <option value="internal">Sadece Kurum İçi</option>
                                    <option value="private">Özel (Sadece Ben)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="notes">Notlar</label>
                                <textarea name="notes" id="notes" rows="3" 
                                          placeholder="Ek notlarınızı buraya yazabilirsiniz..."></textarea>
                            </div>
                        </div>

                        <!-- Form Butonları -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Yayını Kaydet
                            </button>
                            <a href="my_publications.php" class="btn btn-secondary btn-lg">
                                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                                İptal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/publication-form.js"></script>
</body>
</html>