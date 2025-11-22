<?php
/**
 * Add Publication Page
 * 
 * Form for adding new publications with dynamic fields based on type
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');

// Verify faculty role
if ($_SESSION['role'] !== 'faculty') {
    header("Location: ../dashboard.php");
    exit();
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Get publication types
try {
    $stmt = $pdo->query("SELECT * FROM publication_types WHERE is_active = 1 ORDER BY display_order, type_name_en");
    $publication_types = $stmt->fetchAll();
} catch (Exception $e) {
    $errors[] = "Error loading publication types: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $type_id = cleanInput($_POST['type_id'] ?? '');
    $title = cleanInput($_POST['title'] ?? '');
    $authors = cleanInput($_POST['authors'] ?? '');
    $publication_year = cleanInput($_POST['publication_year'] ?? '');
    $publication_date = cleanInput($_POST['publication_date'] ?? '');
    
    // Required fields validation
    if (empty($type_id)) $errors[] = "Publication type is required.";
    if (empty($title)) $errors[] = "Title is required.";
    if (empty($authors)) $errors[] = "Authors are required.";
    if (empty($publication_year)) $errors[] = "Publication year is required.";
    
    if (empty($errors)) {
        try {
            // Prepare data - handle nullable and enum fields properly
            $data = [
                'user_id' => $user_id,
                'type_id' => $type_id,
                'title' => $title,
                'authors' => $authors,
                'publication_year' => $publication_year,
                'publication_date' => $publication_date ?: null,
                'abstract' => cleanInput($_POST['abstract'] ?? '') ?: null,
                'keywords' => cleanInput($_POST['keywords'] ?? '') ?: null,
                'language' => cleanInput($_POST['language'] ?? 'English'),
                'journal_name' => cleanInput($_POST['journal_name'] ?? '') ?: null,
                'volume' => cleanInput($_POST['volume'] ?? '') ?: null,
                'issue' => cleanInput($_POST['issue'] ?? '') ?: null,
                'pages' => cleanInput($_POST['pages'] ?? '') ?: null,
                'issn' => cleanInput($_POST['issn'] ?? '') ?: null,
                'journal_quartile' => !empty($_POST['journal_quartile']) ? cleanInput($_POST['journal_quartile']) : null,
                'impact_factor' => !empty($_POST['impact_factor']) ? floatval($_POST['impact_factor']) : null,
                'conference_name' => cleanInput($_POST['conference_name'] ?? '') ?: null,
                'conference_location' => cleanInput($_POST['conference_location'] ?? '') ?: null,
                'conference_start_date' => !empty($_POST['conference_start_date']) ? cleanInput($_POST['conference_start_date']) : null,
                'conference_end_date' => !empty($_POST['conference_end_date']) ? cleanInput($_POST['conference_end_date']) : null,
                'presentation_type' => !empty($_POST['presentation_type']) ? cleanInput($_POST['presentation_type']) : null,
                'proceedings_published' => isset($_POST['proceedings_published']) ? 1 : 0,
                'publisher' => cleanInput($_POST['publisher'] ?? '') ?: null,
                'isbn' => cleanInput($_POST['isbn'] ?? '') ?: null,
                'edition' => cleanInput($_POST['edition'] ?? '') ?: null,
                'total_pages' => !empty($_POST['total_pages']) ? intval($_POST['total_pages']) : null,
                'book_type' => !empty($_POST['book_type']) ? cleanInput($_POST['book_type']) : null,
                'chapter_title' => cleanInput($_POST['chapter_title'] ?? '') ?: null,
                'chapter_pages' => cleanInput($_POST['chapter_pages'] ?? '') ?: null
            ];
            
            $sql = "INSERT INTO publications (
                user_id, type_id, title, authors, publication_year, publication_date,
                abstract, keywords, language, journal_name, volume, issue, pages, issn,
                journal_quartile, impact_factor, conference_name, conference_location,
                conference_start_date, conference_end_date, presentation_type,
                proceedings_published, publisher, isbn, edition, total_pages,
                book_type, chapter_title, chapter_pages
            ) VALUES (
                :user_id, :type_id, :title, :authors, :publication_year, :publication_date,
                :abstract, :keywords, :language, :journal_name, :volume, :issue, :pages, :issn,
                :journal_quartile, :impact_factor, :conference_name, :conference_location,
                :conference_start_date, :conference_end_date, :presentation_type,
                :proceedings_published, :publisher, :isbn, :edition, :total_pages,
                :book_type, :chapter_title, :chapter_pages
            )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            $success = "Publication added successfully!";
            
            // Redirect after 2 seconds
            header("refresh:2;url=my_publications.php");
            
        } catch (Exception $e) {
            error_log("Add publication error: " . $e->getMessage());
            error_log("Data being sent: " . print_r($data, true));
            $errors[] = "Error saving publication: " . $e->getMessage();
        }
    }
}

$page_title = "Add New Publication";
include 'faculty_header.php';
?>

<style>
/* Dynamic field visibility */
.dynamic-field {
    display: none;
}

.dynamic-field.show {
    display: block;
}

.form-row .dynamic-field.show {
    display: block;
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>Add New Publication</h1>
        <p class="text-muted">Fill in the details of your publication</p>
    </div>
    <a href="my_publications.php" class="btn btn-secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width: 18px; height: 18px;">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Back to My Publications
    </a>
</div>

<!-- Messages -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="margin-bottom: 1.5rem;">
        <strong>Please correct the following errors:</strong>
        <ul style="margin: 0.5rem 0 0 1.5rem;">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem;">
        <?php echo htmlspecialchars($success); ?>
        <br><small>Redirecting to My Publications...</small>
    </div>
<?php endif; ?>

<!-- Publication Form -->
<form method="POST" action="add_publication.php" id="publicationForm">
    
    <!-- Basic Information -->
    <div class="form-section">
        <h2 class="form-section-title">
            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            Basic Information
        </h2>

        <div class="form-row">
            <div class="form-group">
                <label for="type_id">Publication Type <span class="required">*</span></label>
                <select name="type_id" id="type_id" required>
                    <option value="">Select publication type...</option>
                    <?php foreach ($publication_types as $type): ?>
                        <option value="<?php echo $type['type_id']; ?>">
                            <?php echo htmlspecialchars($type['type_name_en']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="language">Language <span class="required">*</span></label>
                <select name="language" id="language">
                    <option value="English" selected>English</option>
                    <option value="Turkish">Turkish</option>
                    <option value="Macedonian">Macedonian</option>
                    <option value="Albanian">Albanian</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="title">Title <span class="required">*</span></label>
            <input type="text" name="title" id="title" required 
                   placeholder="Enter the full title of the publication"
                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="authors">Authors <span class="required">*</span></label>
            <input type="text" name="authors" id="authors" required 
                   placeholder="e.g., Smith, J., Doe, A., & Johnson, B."
                   value="<?php echo isset($_POST['authors']) ? htmlspecialchars($_POST['authors']) : ''; ?>">
            <small class="form-help">Enter all authors separated by commas. Use your name as it appears in the publication.</small>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="publication_year">Publication Year <span class="required">*</span></label>
                <input type="number" name="publication_year" id="publication_year" required 
                       min="1900" max="<?php echo date('Y') + 1; ?>"
                       value="<?php echo isset($_POST['publication_year']) ? htmlspecialchars($_POST['publication_year']) : date('Y'); ?>">
            </div>

            <div class="form-group">
                <label for="publication_date">Publication Date</label>
                <input type="date" name="publication_date" id="publication_date"
                       value="<?php echo isset($_POST['publication_date']) ? htmlspecialchars($_POST['publication_date']) : ''; ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="abstract">Abstract</label>
            <textarea name="abstract" id="abstract" rows="6" 
                      placeholder="Enter the abstract of your publication..."><?php echo isset($_POST['abstract']) ? htmlspecialchars($_POST['abstract']) : ''; ?></textarea>
        </div>

        <div class="form-group">
            <label for="keywords">Keywords</label>
            <input type="text" name="keywords" id="keywords" 
                   placeholder="e.g., machine learning, artificial intelligence, neural networks"
                   value="<?php echo isset($_POST['keywords']) ? htmlspecialchars($_POST['keywords']) : ''; ?>">
            <small class="form-help">Enter keywords separated by commas</small>
        </div>
    </div>

    <!-- Journal Article Fields -->
    <div class="form-section dynamic-field" id="journal-fields">
        <h2 class="form-section-title">
            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/>
            </svg>
            Journal Information
        </h2>

        <div class="form-row">
            <div class="form-group">
                <label for="journal_name">Journal Name</label>
                <input type="text" name="journal_name" id="journal_name" 
                       placeholder="Enter journal name"
                       value="<?php echo isset($_POST['journal_name']) ? htmlspecialchars($_POST['journal_name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="issn">ISSN</label>
                <input type="text" name="issn" id="issn" 
                       placeholder="e.g., 1234-5678"
                       value="<?php echo isset($_POST['issn']) ? htmlspecialchars($_POST['issn']) : ''; ?>">
            </div>
        </div>

        <div class="form-row-3 form-row">
            <div class="form-group">
                <label for="volume">Volume</label>
                <input type="text" name="volume" id="volume" 
                       placeholder="e.g., 25"
                       value="<?php echo isset($_POST['volume']) ? htmlspecialchars($_POST['volume']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="issue">Issue</label>
                <input type="text" name="issue" id="issue" 
                       placeholder="e.g., 3"
                       value="<?php echo isset($_POST['issue']) ? htmlspecialchars($_POST['issue']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="pages">Pages</label>
                <input type="text" name="pages" id="pages" 
                       placeholder="e.g., 123-145"
                       value="<?php echo isset($_POST['pages']) ? htmlspecialchars($_POST['pages']) : ''; ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="journal_quartile">Journal Quartile</label>
                <select name="journal_quartile" id="journal_quartile">
                    <option value="">Select quartile...</option>
                    <option value="Q1">Q1</option>
                    <option value="Q2">Q2</option>
                    <option value="Q3">Q3</option>
                    <option value="Q4">Q4</option>
                </select>
            </div>

            <div class="form-group">
                <label for="impact_factor">Impact Factor</label>
                <input type="number" name="impact_factor" id="impact_factor" 
                       step="0.001" min="0" placeholder="e.g., 3.456"
                       value="<?php echo isset($_POST['impact_factor']) ? htmlspecialchars($_POST['impact_factor']) : ''; ?>">
            </div>
        </div>
    </div>

    <!-- Conference Fields -->
    <div class="form-section dynamic-field" id="conference-fields">
        <h2 class="form-section-title">
            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Conference Information
        </h2>

        <div class="form-group">
            <label for="conference_name">Conference Name</label>
            <input type="text" name="conference_name" id="conference_name" 
                   placeholder="Enter conference name"
                   value="<?php echo isset($_POST['conference_name']) ? htmlspecialchars($_POST['conference_name']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="conference_location">Conference Location</label>
            <input type="text" name="conference_location" id="conference_location" 
                   placeholder="City, Country"
                   value="<?php echo isset($_POST['conference_location']) ? htmlspecialchars($_POST['conference_location']) : ''; ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="conference_start_date">Start Date</label>
                <input type="date" name="conference_start_date" id="conference_start_date"
                       value="<?php echo isset($_POST['conference_start_date']) ? htmlspecialchars($_POST['conference_start_date']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="conference_end_date">End Date</label>
                <input type="date" name="conference_end_date" id="conference_end_date"
                       value="<?php echo isset($_POST['conference_end_date']) ? htmlspecialchars($_POST['conference_end_date']) : ''; ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="presentation_type">Presentation Type</label>
                <select name="presentation_type" id="presentation_type">
                    <option value="">Select type...</option>
                    <option value="oral">Oral Presentation</option>
                    <option value="poster">Poster</option>
                    <option value="keynote">Keynote</option>
                    <option value="workshop">Workshop</option>
                    <option value="panel">Panel</option>
                </select>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="proceedings_published" value="1">
                    <span>Proceedings Published</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Book Fields -->
    <div class="form-section dynamic-field" id="book-fields">
        <h2 class="form-section-title">
            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/>
            </svg>
            Book Information
        </h2>

        <div class="form-row">
            <div class="form-group">
                <label for="publisher">Publisher</label>
                <input type="text" name="publisher" id="publisher" 
                       placeholder="Enter publisher name"
                       value="<?php echo isset($_POST['publisher']) ? htmlspecialchars($_POST['publisher']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="isbn">ISBN</label>
                <input type="text" name="isbn" id="isbn" 
                       placeholder="e.g., 978-3-16-148410-0"
                       value="<?php echo isset($_POST['isbn']) ? htmlspecialchars($_POST['isbn']) : ''; ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="book_type">Book Type</label>
                <select name="book_type" id="book_type">
                    <option value="">Select type...</option>
                    <option value="authored">Authored</option>
                    <option value="edited">Edited</option>
                    <option value="chapter">Chapter</option>
                </select>
            </div>

            <div class="form-group">
                <label for="edition">Edition</label>
                <input type="text" name="edition" id="edition" 
                       placeholder="e.g., 1st, 2nd"
                       value="<?php echo isset($_POST['edition']) ? htmlspecialchars($_POST['edition']) : ''; ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="total_pages">Total Pages</label>
                <input type="number" name="total_pages" id="total_pages" 
                       placeholder="e.g., 350"
                       value="<?php echo isset($_POST['total_pages']) ? htmlspecialchars($_POST['total_pages']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="chapter_pages">Chapter Pages</label>
                <input type="text" name="chapter_pages" id="chapter_pages" 
                       placeholder="e.g., 25-48"
                       value="<?php echo isset($_POST['chapter_pages']) ? htmlspecialchars($_POST['chapter_pages']) : ''; ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="chapter_title">Chapter Title</label>
            <input type="text" name="chapter_title" id="chapter_title" 
                   placeholder="Enter chapter title (if applicable)"
                   value="<?php echo isset($_POST['chapter_title']) ? htmlspecialchars($_POST['chapter_title']) : ''; ?>">
        </div>
    </div>

    <!-- Form Actions -->
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width: 18px; height: 18px;">
                <path d="M5 13l4 4L19 7"/>
            </svg>
            Save Publication
        </button>
        <a href="my_publications.php" class="btn btn-secondary">Cancel</a>
    </div>

</form>

<script>
// Dynamic field display based on publication type
document.getElementById('type_id').addEventListener('change', function() {
    const typeId = this.value;
    const selectedOption = this.options[this.selectedIndex];
    const typeName = selectedOption.text.toLowerCase();
    
    // Hide all dynamic fields
    document.querySelectorAll('.dynamic-field').forEach(field => {
        field.classList.remove('show');
    });
    
    // Show relevant fields based on type
    if (typeName.includes('journal') || typeName.includes('article')) {
        document.getElementById('journal-fields').classList.add('show');
    }
    
    if (typeName.includes('conference') || typeName.includes('proceeding')) {
        document.getElementById('conference-fields').classList.add('show');
    }
    
    if (typeName.includes('book') || typeName.includes('chapter')) {
        document.getElementById('book-fields').classList.add('show');
    }
});

// Form validation
document.getElementById('publicationForm').addEventListener('submit', function(e) {
    const typeId = document.getElementById('type_id').value;
    const title = document.getElementById('title').value.trim();
    const authors = document.getElementById('authors').value.trim();
    const year = document.getElementById('publication_year').value;
    
    if (!typeId || !title || !authors || !year) {
        e.preventDefault();
        alert('Please fill in all required fields marked with *');
        return false;
    }
});
</script>

<?php
include 'faculty_footer.php';
?>
