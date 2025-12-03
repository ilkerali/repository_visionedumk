<?php
/**
 * CV Information Save Handler
 * 
 * CV bilgilerini kaydetme, güncelleme ve silme işlemleri
 * Save, update and delete operations for CV information
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        
        // ============================================================
        // PROFILE PHOTO UPLOAD
        // ============================================================
        case 'upload_profile_photo':
            if (!isset($_FILES['profile_photo'])) {
                throw new Exception('No file uploaded');
            }
            
            $file = $_FILES['profile_photo'];
            
            // Validate file
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Only JPG and PNG files are allowed');
            }
            
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception('File size must be less than 2MB');
            }
            
            // Create uploads directory if it doesn't exist
            $uploadDir = '../uploads/profile_photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to upload file');
            }
            
            // Update user profile_image in database
            $relativePath = 'uploads/profile_photos/' . $filename;
            $stmt = $pdo->prepare("UPDATE users SET profile_image = :profile_image WHERE user_id = :user_id");
            $stmt->execute([
                ':profile_image' => $relativePath,
                ':user_id' => $userId
            ]);
            
            // Delete old photo if exists
            $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            $oldImage = $stmt->fetchColumn();
            
            if ($oldImage && $oldImage !== $relativePath && file_exists('../' . $oldImage)) {
                unlink('../' . $oldImage);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Profile photo uploaded successfully',
                'image_path' => $relativePath
            ]);
            exit;
        
        // ============================================================
        // PERSONAL INFORMATION
        // ===========================================================
        case 'save_personal_info':
            $data = [
                'user_id' => $userId,
                'date_of_birth' => !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
                'place_of_birth' => cleanInput($_POST['place_of_birth'] ?? ''),
                'nationality' => cleanInput($_POST['nationality'] ?? ''),
                'gender' => cleanInput($_POST['gender'] ?? ''),
                'marital_status' => cleanInput($_POST['marital_status'] ?? ''),
                'cv_phone' => cleanInput($_POST['cv_phone'] ?? ''),
                'cv_email' => cleanInput($_POST['cv_email'] ?? ''),
                'cv_address' => cleanInput($_POST['cv_address'] ?? ''),
                'cv_city' => cleanInput($_POST['cv_city'] ?? ''),
                'cv_postal_code' => cleanInput($_POST['cv_postal_code'] ?? ''),
                'cv_country' => cleanInput($_POST['cv_country'] ?? '')
            ];
            
            // Profil var mı kontrol et
            $stmt = $pdo->prepare("SELECT profile_id FROM cv_profiles WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Güncelle
                $sql = "UPDATE cv_profiles SET 
                        date_of_birth = :date_of_birth,
                        place_of_birth = :place_of_birth,
                        nationality = :nationality,
                        gender = :gender,
                        marital_status = :marital_status,
                        cv_phone = :cv_phone,
                        cv_email = :cv_email,
                        cv_address = :cv_address,
                        cv_city = :cv_city,
                        cv_postal_code = :cv_postal_code,
                        cv_country = :cv_country,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE user_id = :user_id";
            } else {
                // Yeni kayıt
                $sql = "INSERT INTO cv_profiles (
                        user_id, date_of_birth, place_of_birth, nationality, gender, 
                        marital_status, cv_phone, cv_email, cv_address, cv_city, 
                        cv_postal_code, cv_country
                    ) VALUES (
                        :user_id, :date_of_birth, :place_of_birth, :nationality, :gender,
                        :marital_status, :cv_phone, :cv_email, :cv_address, :cv_city,
                        :cv_postal_code, :cv_country
                    )";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            echo json_encode(['success' => true, 'message' => 'Personal information saved successfully']);
            break;
            
        // ============================================================
        // WEB LINKS
        // ============================================================
        case 'get_web_link':
            $linkId = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM cv_web_links WHERE link_id = :link_id AND user_id = :user_id");
            $stmt->execute([':link_id' => $linkId, ':user_id' => $userId]);
            $data = $stmt->fetch();
            
            if ($data) {
                echo json_encode($data);
            } else {
                echo json_encode(['error' => 'Link not found']);
            }
            break;
            
        case 'save_web_link':
            $linkId = !empty($_POST['link_id']) ? (int)$_POST['link_id'] : null;
            $data = [
                'user_id' => $userId,
                'link_type' => cleanInput($_POST['link_type']),
                'link_label' => cleanInput($_POST['link_label']),
                'link_url' => cleanInput($_POST['link_url']),
                'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
                'display_order' => 0
            ];
            
            if ($linkId) {
                // Güncelle
                $sql = "UPDATE cv_web_links SET 
                        link_type = :link_type,
                        link_label = :link_label,
                        link_url = :link_url,
                        is_visible = :is_visible,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE link_id = :link_id AND user_id = :user_id";
                $data['link_id'] = $linkId;
            } else {
                // Yeni kayıt - son sırayı bul
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM cv_web_links WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $data['display_order'] = $stmt->fetch()['next_order'];
                
                $sql = "INSERT INTO cv_web_links (user_id, link_type, link_label, link_url, is_visible, display_order)
                        VALUES (:user_id, :link_type, :link_label, :link_url, :is_visible, :display_order)";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            echo json_encode(['success' => true, 'message' => 'Web link saved successfully']);
            break;
            
        case 'delete_web_link':
            $linkId = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM cv_web_links WHERE link_id = :link_id AND user_id = :user_id");
            $stmt->execute([':link_id' => $linkId, ':user_id' => $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Web link deleted successfully']);
            break;
            
        // ============================================================
        // WORK EXPERIENCE
        // ============================================================
        case 'get_work_experience':
            $expId = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM cv_work_experience WHERE experience_id = :experience_id AND user_id = :user_id");
            $stmt->execute([':experience_id' => $expId, ':user_id' => $userId]);
            $data = $stmt->fetch();
            
            if ($data) {
                echo json_encode($data);
            } else {
                echo json_encode(['error' => 'Experience not found']);
            }
            break;
            
        case 'save_work_experience':
            $expId = !empty($_POST['experience_id']) ? (int)$_POST['experience_id'] : null;
            $data = [
                'user_id' => $userId,
                'job_title' => cleanInput($_POST['job_title']),
                'employer' => cleanInput($_POST['employer']),
                'location' => cleanInput($_POST['location'] ?? ''),
                'start_date' => $_POST['start_date'],
                'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                'is_current' => isset($_POST['is_current']) ? 1 : 0,
                'employment_type' => cleanInput($_POST['employment_type'] ?? ''),
                'description' => cleanInput($_POST['description'] ?? ''),
                'is_visible' => isset($_POST['is_visible']) ? 1 : 0
            ];
            
            if ($expId) {
                // Güncelle
                $sql = "UPDATE cv_work_experience SET 
                        job_title = :job_title,
                        employer = :employer,
                        location = :location,
                        start_date = :start_date,
                        end_date = :end_date,
                        is_current = :is_current,
                        employment_type = :employment_type,
                        description = :description,
                        is_visible = :is_visible,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE experience_id = :experience_id AND user_id = :user_id";
                $data['experience_id'] = $expId;
            } else {
                // Yeni kayıt
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM cv_work_experience WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $data['display_order'] = $stmt->fetch()['next_order'];
                
                $sql = "INSERT INTO cv_work_experience (
                        user_id, job_title, employer, location, start_date, end_date, 
                        is_current, employment_type, description, is_visible, display_order
                    ) VALUES (
                        :user_id, :job_title, :employer, :location, :start_date, :end_date,
                        :is_current, :employment_type, :description, :is_visible, :display_order
                    )";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            echo json_encode(['success' => true, 'message' => 'Work experience saved successfully']);
            break;
            
        case 'delete_work_experience':
            $expId = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM cv_work_experience WHERE experience_id = :experience_id AND user_id = :user_id");
            $stmt->execute([':experience_id' => $expId, ':user_id' => $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Work experience deleted successfully']);
            break;
            
        // ============================================================
        // EDUCATION
        // ============================================================
        case 'get_education':
            $eduId = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM cv_education WHERE education_id = :education_id AND user_id = :user_id");
            $stmt->execute([':education_id' => $eduId, ':user_id' => $userId]);
            $data = $stmt->fetch();
            
            if ($data) {
                echo json_encode($data);
            } else {
                echo json_encode(['error' => 'Education not found']);
            }
            break;
            
        case 'save_education':
            $eduId = !empty($_POST['education_id']) ? (int)$_POST['education_id'] : null;
            $data = [
                'user_id' => $userId,
                'degree' => cleanInput($_POST['degree']),
                'field_of_study' => cleanInput($_POST['field_of_study'] ?? ''),
                'institution' => cleanInput($_POST['institution']),
                'location' => cleanInput($_POST['location'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                'graduation_year' => !empty($_POST['graduation_year']) ? (int)$_POST['graduation_year'] : null,
                'thesis_title' => cleanInput($_POST['thesis_title'] ?? ''),
                'thesis_supervisor' => cleanInput($_POST['thesis_supervisor'] ?? ''),
                'grade' => cleanInput($_POST['grade'] ?? ''),
                'honors' => cleanInput($_POST['honors'] ?? ''),
                'description' => cleanInput($_POST['description'] ?? ''),
                'is_visible' => isset($_POST['is_visible']) ? 1 : 0
            ];
            
            if ($eduId) {
                // Güncelle
                $sql = "UPDATE cv_education SET 
                        degree = :degree,
                        field_of_study = :field_of_study,
                        institution = :institution,
                        location = :location,
                        start_date = :start_date,
                        end_date = :end_date,
                        graduation_year = :graduation_year,
                        thesis_title = :thesis_title,
                        thesis_supervisor = :thesis_supervisor,
                        grade = :grade,
                        honors = :honors,
                        description = :description,
                        is_visible = :is_visible,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE education_id = :education_id AND user_id = :user_id";
                $data['education_id'] = $eduId;
            } else {
                // Yeni kayıt
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM cv_education WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $data['display_order'] = $stmt->fetch()['next_order'];
                
                $sql = "INSERT INTO cv_education (
                        user_id, degree, field_of_study, institution, location, start_date, 
                        end_date, graduation_year, thesis_title, thesis_supervisor, grade, 
                        honors, description, is_visible, display_order
                    ) VALUES (
                        :user_id, :degree, :field_of_study, :institution, :location, :start_date,
                        :end_date, :graduation_year, :thesis_title, :thesis_supervisor, :grade,
                        :honors, :description, :is_visible, :display_order
                    )";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            echo json_encode(['success' => true, 'message' => 'Education saved successfully']);
            break;
            
        case 'delete_education':
            $eduId = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM cv_education WHERE education_id = :education_id AND user_id = :user_id");
            $stmt->execute([':education_id' => $eduId, ':user_id' => $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Education deleted successfully']);
            break;
            
        // ============================================================
        // LANGUAGE SKILLS
        // ============================================================
        case 'get_language':
            $langId = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM cv_language_skills WHERE language_id = :language_id AND user_id = :user_id");
            $stmt->execute([':language_id' => $langId, ':user_id' => $userId]);
            $data = $stmt->fetch();
            
            if ($data) {
                echo json_encode($data);
            } else {
                echo json_encode(['error' => 'Language not found']);
            }
            break;
            
        case 'save_language':
            $langId = !empty($_POST['language_id']) ? (int)$_POST['language_id'] : null;
            $data = [
                'user_id' => $userId,
                'language' => cleanInput($_POST['language']),
                'is_mother_tongue' => isset($_POST['is_mother_tongue']) ? 1 : 0,
                'listening_level' => cleanInput($_POST['listening_level'] ?? ''),
                'reading_level' => cleanInput($_POST['reading_level'] ?? ''),
                'spoken_interaction_level' => cleanInput($_POST['spoken_interaction_level'] ?? ''),
                'spoken_production_level' => cleanInput($_POST['spoken_production_level'] ?? ''),
                'writing_level' => cleanInput($_POST['writing_level'] ?? ''),
                'certificates' => cleanInput($_POST['certificates'] ?? ''),
                'is_visible' => isset($_POST['is_visible']) ? 1 : 0
            ];
            
            if ($langId) {
                // Güncelle
                $sql = "UPDATE cv_language_skills SET 
                        language = :language,
                        is_mother_tongue = :is_mother_tongue,
                        listening_level = :listening_level,
                        reading_level = :reading_level,
                        spoken_interaction_level = :spoken_interaction_level,
                        spoken_production_level = :spoken_production_level,
                        writing_level = :writing_level,
                        certificates = :certificates,
                        is_visible = :is_visible,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE language_id = :language_id AND user_id = :user_id";
                $data['language_id'] = $langId;
            } else {
                // Yeni kayıt
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM cv_language_skills WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $data['display_order'] = $stmt->fetch()['next_order'];
                
                $sql = "INSERT INTO cv_language_skills (
                        user_id, language, is_mother_tongue, listening_level, reading_level,
                        spoken_interaction_level, spoken_production_level, writing_level,
                        certificates, is_visible, display_order
                    ) VALUES (
                        :user_id, :language, :is_mother_tongue, :listening_level, :reading_level,
                        :spoken_interaction_level, :spoken_production_level, :writing_level,
                        :certificates, :is_visible, :display_order
                    )";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            echo json_encode(['success' => true, 'message' => 'Language skill saved successfully']);
            break;
            
        case 'delete_language':
            $langId = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM cv_language_skills WHERE language_id = :language_id AND user_id = :user_id");
            $stmt->execute([':language_id' => $langId, ':user_id' => $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Language skill deleted successfully']);
            break;
            
        // ============================================================
        // PERSONAL SKILLS
        // ============================================================
        case 'get_personal_skill':
            $skillId = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM cv_personal_skills WHERE skill_id = :skill_id AND user_id = :user_id");
            $stmt->execute([':skill_id' => $skillId, ':user_id' => $userId]);
            $data = $stmt->fetch();
            
            if ($data) {
                echo json_encode($data);
            } else {
                echo json_encode(['error' => 'Skill not found']);
            }
            break;
            
        case 'save_personal_skill':
            $skillId = !empty($_POST['skill_id']) ? (int)$_POST['skill_id'] : null;
            $data = [
                'user_id' => $userId,
                'skill_category' => cleanInput($_POST['skill_category']),
                'skill_subcategory' => cleanInput($_POST['skill_subcategory'] ?? ''),
                'skill_description' => cleanInput($_POST['skill_description']),
                'skill_type' => cleanInput($_POST['skill_type'] ?? ''),
                'proficiency_level' => cleanInput($_POST['proficiency_level'] ?? ''),
                'is_visible' => isset($_POST['is_visible']) ? 1 : 0
            ];
            
            if ($skillId) {
                // Güncelle
                $sql = "UPDATE cv_personal_skills SET 
                        skill_category = :skill_category,
                        skill_subcategory = :skill_subcategory,
                        skill_description = :skill_description,
                        skill_type = :skill_type,
                        proficiency_level = :proficiency_level,
                        is_visible = :is_visible,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE skill_id = :skill_id AND user_id = :user_id";
                $data['skill_id'] = $skillId;
            } else {
                // Yeni kayıt
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM cv_personal_skills WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $data['display_order'] = $stmt->fetch()['next_order'];
                
                $sql = "INSERT INTO cv_personal_skills (
                        user_id, skill_category, skill_subcategory, skill_description,
                        skill_type, proficiency_level, is_visible, display_order
                    ) VALUES (
                        :user_id, :skill_category, :skill_subcategory, :skill_description,
                        :skill_type, :proficiency_level, :is_visible, :display_order
                    )";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            echo json_encode(['success' => true, 'message' => 'Personal skill saved successfully']);
            break;
            
        case 'delete_personal_skill':
            $skillId = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM cv_personal_skills WHERE skill_id = :skill_id AND user_id = :user_id");
            $stmt->execute([':skill_id' => $skillId, ':user_id' => $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Personal skill deleted successfully']);
            break;
            
        // ============================================================
        // ADDITIONAL INFORMATION
        // ============================================================
        case 'get_additional_info':
            $infoId = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM cv_additional_info WHERE info_id = :info_id AND user_id = :user_id");
            $stmt->execute([':info_id' => $infoId, ':user_id' => $userId]);
            $data = $stmt->fetch();
            
            if ($data) {
                echo json_encode($data);
            } else {
                echo json_encode(['error' => 'Information not found']);
            }
            break;
            
        case 'save_additional_info':
            $infoId = !empty($_POST['info_id']) ? (int)$_POST['info_id'] : null;
            $data = [
                'user_id' => $userId,
                'info_category' => cleanInput($_POST['info_category']),
                'info_subcategory' => cleanInput($_POST['info_subcategory'] ?? ''),
                'info_content' => cleanInput($_POST['info_content']),
                'info_type' => cleanInput($_POST['info_type'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                'is_visible' => isset($_POST['is_visible']) ? 1 : 0
            ];
            
            if ($infoId) {
                // Güncelle
                $sql = "UPDATE cv_additional_info SET 
                        info_category = :info_category,
                        info_subcategory = :info_subcategory,
                        info_content = :info_content,
                        info_type = :info_type,
                        start_date = :start_date,
                        end_date = :end_date,
                        is_visible = :is_visible,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE info_id = :info_id AND user_id = :user_id";
                $data['info_id'] = $infoId;
            } else {
                // Yeni kayıt
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM cv_additional_info WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $data['display_order'] = $stmt->fetch()['next_order'];
                
                $sql = "INSERT INTO cv_additional_info (
                        user_id, info_category, info_subcategory, info_content,
                        info_type, start_date, end_date, is_visible, display_order
                    ) VALUES (
                        :user_id, :info_category, :info_subcategory, :info_content,
                        :info_type, :start_date, :end_date, :is_visible, :display_order
                    )";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            echo json_encode(['success' => true, 'message' => 'Additional information saved successfully']);
            break;
            
        case 'delete_additional_info':
            $infoId = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM cv_additional_info WHERE info_id = :info_id AND user_id = :user_id");
            $stmt->execute([':info_id' => $infoId, ':user_id' => $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Additional information deleted successfully']);
            break;
            
        // ============================================================
        // DRIVING LICENSES
        // ============================================================
        case 'get_driving_license':
            $licenseId = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM cv_driving_licenses WHERE license_id = :license_id AND user_id = :user_id");
            $stmt->execute([':license_id' => $licenseId, ':user_id' => $userId]);
            $data = $stmt->fetch();
            
            if ($data) {
                echo json_encode($data);
            } else {
                echo json_encode(['error' => 'License not found']);
            }
            break;
            
        case 'save_driving_license':
            $licenseId = !empty($_POST['license_id']) ? (int)$_POST['license_id'] : null;
            $data = [
                'user_id' => $userId,
                'license_category' => cleanInput($_POST['license_category']),
                'issue_date' => !empty($_POST['issue_date']) ? $_POST['issue_date'] : null,
                'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
                'issuing_country' => cleanInput($_POST['issuing_country'] ?? ''),
                'is_visible' => isset($_POST['is_visible']) ? 1 : 0
            ];
            
            if ($licenseId) {
                // Güncelle
                $sql = "UPDATE cv_driving_licenses SET 
                        license_category = :license_category,
                        issue_date = :issue_date,
                        expiry_date = :expiry_date,
                        issuing_country = :issuing_country,
                        is_visible = :is_visible,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE license_id = :license_id AND user_id = :user_id";
                $data['license_id'] = $licenseId;
            } else {
                // Yeni kayıt
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM cv_driving_licenses WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $data['display_order'] = $stmt->fetch()['next_order'];
                
                $sql = "INSERT INTO cv_driving_licenses (
                        user_id, license_category, issue_date, expiry_date,
                        issuing_country, is_visible, display_order
                    ) VALUES (
                        :user_id, :license_category, :issue_date, :expiry_date,
                        :issuing_country, :is_visible, :display_order
                    )";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            echo json_encode(['success' => true, 'message' => 'Driving license saved successfully']);
            break;
            
        case 'delete_driving_license':
            $licenseId = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM cv_driving_licenses WHERE license_id = :license_id AND user_id = :user_id");
            $stmt->execute([':license_id' => $licenseId, ':user_id' => $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Driving license deleted successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("CV Save Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("CV Save Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>