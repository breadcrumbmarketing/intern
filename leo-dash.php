<?php
/*
Template Name: Leo Dashboard Form
*/

// Security check
if (!is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

get_header();

// Message handling
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leo_submit'])) {
    // Nonce verification
    if (!wp_verify_nonce($_POST['leo_nonce'], 'leo_form_nonce')) {
        die('Sicherheitsprüfung fehlgeschlagen');
    }

    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'leo';
        
        // Handle file uploads
        $leo_image = '';
        $leo_video = '';
        $leo_doc = '';
        
        if (!empty($_FILES['leo_image']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            $image_id = media_handle_upload('leo_image', 0);
            if (!is_wp_error($image_id)) {
                $leo_image = wp_get_attachment_url($image_id);
            }
        }
        
        if (!empty($_FILES['leo_video']['name'])) {
            $video_id = media_handle_upload('leo_video', 0);
            if (!is_wp_error($video_id)) {
                $leo_video = wp_get_attachment_url($video_id);
            }
        }
        
        if (!empty($_FILES['leo_doc']['name'])) {
            $doc_id = media_handle_upload('leo_doc', 0);
            if (!is_wp_error($doc_id)) {
                $leo_doc = wp_get_attachment_url($doc_id);
            }
        }
        
        // Insert data
        $wpdb->insert(
            $table_name,
            array(
                'leo_title' => sanitize_text_field($_POST['leo_title']),
                'leo_description' => wp_kses_post($_POST['leo_description']),
                'start_time' => sanitize_text_field($_POST['start_time']),
                'end_time' => sanitize_text_field($_POST['end_time']),
                'leo_image' => $leo_image,
                'leo_video' => $leo_video,
                'leo_doc' => $leo_doc,
                'leo_moreinfo' => wp_kses_post($_POST['leo_moreinfo'])
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($wpdb->insert_id) {
            $message = 'Eintrag erfolgreich hinzugefügt!';
            $message_type = 'success';
        } else {
            $message = 'Fehler beim Speichern des Eintrags.';
            $message_type = 'danger';
        }
    } catch (Exception $e) {
        $message = 'Fehler: ' . $e->getMessage();
        $message_type = 'danger';
    }
}
?>

<!-- Custom CSS -->
<style>
.leo-dashboard {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 2rem;
}

.leo-user-info {
    background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.leo-form-container {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.leo-form-title {
    color: #1F2937;
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #E5E7EB;
}

.form-control, .form-select {
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 1rem;
}

.form-label {
    font-weight: 500;
    color: #4B5563;
    margin-bottom: 0.5rem;
}

.btn-primary {
    background: #4F46E5;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #4338CA;
    transform: translateY(-1px);
}

.upload-preview {
    max-width: 200px;
    margin-top: 1rem;
}

@media (max-width: 768px) {
    .leo-dashboard {
        padding: 1rem;
    }
    
    .leo-form-container {
        padding: 1.5rem;
    }
}
</style>

<div class="leo-dashboard">
    <div class="container-fluid">
        <div class="row">
            <!-- User Info -->
            <div class="col-12">
                <div class="leo-user-info d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0"><?php echo wp_get_current_user()->display_name; ?></h4>
                        <small><?php echo wp_get_current_user()->user_email; ?></small>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark">
                            <?php echo date('d.m.Y'); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Message Display -->
            <?php if ($message): ?>
            <div class="col-12">
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Form Container -->
            <div class="col-12">
                <div class="leo-form-container">
                    <h2 class="leo-form-title">Neue Nachricht erstellen</h2>
                    
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('leo_form_nonce', 'leo_nonce'); ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="leo_title" class="form-label">Titel</label>
                                    <input type="text" class="form-control" id="leo_title" name="leo_title" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="leo_description" class="form-label">Kurznachricht</label>
                                    <textarea class="form-control" id="leo_description" name="leo_description" rows="3"></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_time" class="form-label">Startzeit</label>
                                    <input type="datetime-local" class="form-control" id="start_time" name="start_time">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_time" class="form-label">Endzeit</label>
                                    <input type="datetime-local" class="form-control" id="end_time" name="end_time">
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="leo_moreinfo" class="form-label">Zusätzliche Informationen</label>
                                    <textarea class="form-control" id="leo_moreinfo" name="leo_moreinfo" rows="4"></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="leo_image" class="form-label">Bild hochladen</label>
                                    <input type="file" class="form-control" id="leo_image" name="leo_image" accept="image/*">
                                    <div id="image-preview" class="upload-preview"></div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="leo_video" class="form-label">Video hochladen</label>
                                    <input type="file" class="form-control" id="leo_video" name="leo_video" accept="video/*">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="leo_doc" class="form-label">Dokument hochladen</label>
                                    <input type="file" class="form-control" id="leo_doc" name="leo_doc" accept=".pdf,.doc,.docx">
                                </div>
                            </div>
                            
                            <div class="col-12 text-end mt-4">
                                <button type="submit" name="leo_submit" class="btn btn-primary">
                                    Nachricht senden
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for file handling and preview -->
<script>
// Image preview
document.getElementById('leo_image').addEventListener('change', function(e) {
    const preview = document.getElementById('image-preview');
    const file = e.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" class="img-fluid mt-2">`;
        }
        reader.readAsDataURL(file);
    }
});

// File type validation
function validateFileType(input) {
    const file = input.files[0];
    const fileType = file.type;
    let isValid = false;
    
    switch(input.id) {
        case 'leo_image':
            isValid = fileType.match('image.*');
            break;
        case 'leo_video':
            isValid = fileType.match('video.*');
            break;
        case 'leo_doc':
            isValid = fileType.match('application/pdf') || 
                     fileType.match('application/msword') || 
                     fileType.match('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            break;
    }
    
    if (!isValid) {
        alert('Ungültiger Dateityp!');
        input.value = '';
        return false;
    }
    return true;
}

// Add validation to file inputs
document.getElementById('leo_image').addEventListener('change', function() {
    validateFileType(this);
});
document.getElementById('leo_video').addEventListener('change', function() {
    validateFileType(this);
});
document.getElementById('leo_doc').addEventListener('change', function() {
    validateFileType(this);
});
</script>

<?php
get_footer();
?>