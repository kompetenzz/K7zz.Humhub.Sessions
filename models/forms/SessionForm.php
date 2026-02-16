<?php

namespace humhub\modules\sessions\models\forms;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\sessions\models\Session;
use humhub\modules\sessions\models\SessionUser;
use humhub\modules\sessions\services\BackendRegistry;
use humhub\modules\file\models\File;
use humhub\modules\topic\models\Topic;
use humhub\modules\user\models\User;
use humhub\libs\UUID;
use yii\base\Model;
use yii\web\UploadedFile;
use yii\helpers\Inflector;
use Yii;

/**
 * Form model for creating and updating sessions.
 */
class SessionForm extends Model
{
    public const SLUG_PATTERN = '[a-z0-9\-]+';

    // Core fields
    public ?int $id = null;
    public string $backend_type = 'bbb';
    public string $name = '';
    public ?string $title = null;
    public ?string $description = null;
    public string $moderator_pw = '';
    public string $attendee_pw = '';

    // Permissions
    public bool $publicJoin = false;
    public bool $joinByPermissions = true;
    public bool $joinCanStart = false;
    public bool $joinCanModerate = false;
    public bool $moderateByPermissions = true;
    public bool $hasWaitingRoom = false;
    public bool $allowRecording = true;
    public bool $muteOnEntry = false;
    public bool $enabled = true;

    // User assignments
    public $attendeeRefs = [];
    public $moderatorRefs = [];

    // Content
    public $visibility;
    public $hidden;
    public $topics = [];

    // Files
    public ?int $image_file_id = null;
    public $imageUpload = null;
    public ?int $presentation_file_id = null;
    public $presentationUpload = null;
    public ?int $camera_bg_image_file_id = null;
    public $cameraBgImageUpload = null;

    // Remove flags
    public bool $removeImage = false;
    public bool $removePresentation = false;
    public bool $removeCameraBgImage = false;

    // Backend config (JSON)
    public array $backendConfig = [];

    // Internal
    private ?Session $record = null;
    public ?ContentContainerActiveRecord $contentContainer = null;
    private int $creatorId;

    public function init()
    {
        parent::init();

        if ($this->id === null) {
            $this->moderator_pw = Yii::$app->security->generateRandomString(10);
            $this->attendee_pw = Yii::$app->security->generateRandomString(10);
        }
    }

    /**
     * Create a new session form
     */
    public static function create(?ContentContainerActiveRecord $container = null): self
    {
        $form = new self();
        $form->contentContainer = $container;
        $form->creatorId = Yii::$app->user->id;

        // Set default backend (first allowed backend for this container)
        $allowed = BackendRegistry::getAllowedForContainer($container);
        if (!empty($allowed)) {
            $form->backend_type = array_key_first($allowed);
        }

        // Check container settings for default backend
        if ($container !== null) {
            $settings = Yii::$app->getModule('sessions')->settings->contentContainer($container);
            $defaultBackend = $settings->get('defaultBackend');
            if ($defaultBackend && isset($allowed[$defaultBackend])) {
                $form->backend_type = $defaultBackend;
            }
        }

        return $form;
    }

    /**
     * Edit an existing session
     */
    public static function edit(Session $session): self
    {
        $form = new self();
        $form->record = $session;
        $form->contentContainer = $session->content->container ?? null;
        $form->creatorId = $session->creator_user_id;

        // Load values from session
        $form->id = $session->id;
        $form->backend_type = $session->backend_type;
        $form->name = $session->name;
        $form->title = $session->title;
        $form->description = $session->description;
        $form->moderator_pw = $session->moderator_pw;
        $form->attendee_pw = $session->attendee_pw;
        $form->publicJoin = (bool) $session->public_join;
        $form->joinCanStart = (bool) $session->join_can_start;
        $form->joinCanModerate = (bool) $session->join_can_moderate;
        $form->hasWaitingRoom = (bool) $session->has_waitingroom;
        $form->allowRecording = (bool) $session->allow_recording;
        $form->muteOnEntry = (bool) $session->mute_on_entry;
        $form->enabled = (bool) $session->enabled;
        $form->image_file_id = $session->image_file_id;
        $form->presentation_file_id = $session->presentation_file_id;
        $form->camera_bg_image_file_id = $session->camera_bg_image_file_id;
        $form->backendConfig = $session->getBackendConfig();

        // Load visibility
        if ($session->content) {
            $form->visibility = $session->content->visibility;
            $form->hidden = $session->content->hidden;
        }

        // Load user assignments (as GUIDs for UserPickerField)
        $attendeeGuids = SessionUser::find()
            ->alias('su')
            ->innerJoin(User::tableName() . ' u', 'su.user_id = u.id')
            ->where(['su.session_id' => $session->id, 'su.role' => 'attendee'])
            ->select('u.guid')
            ->column();
        $moderatorGuids = SessionUser::find()
            ->alias('su')
            ->innerJoin(User::tableName() . ' u', 'su.user_id = u.id')
            ->where(['su.session_id' => $session->id, 'su.role' => 'moderator'])
            ->select('u.guid')
            ->column();

        $form->attendeeRefs = $attendeeGuids;
        $form->moderatorRefs = $moderatorGuids;
        $form->joinByPermissions = empty($attendeeGuids);
        $form->moderateByPermissions = empty($moderatorGuids);

        // Load topics
        if ($session->content) {
            $form->topics = $session->content->getTags()
                ->where(['module_id' => 'topic'])
                ->select('id')
                ->column();
        }

        return $form;
    }

    public function rules(): array
    {
        return [
            [['name', 'backend_type', 'moderator_pw', 'attendee_pw'], 'required'],
            [['name'], 'string', 'max' => 100],
            [['name'], 'match', 'pattern' => '/^' . self::SLUG_PATTERN . '$/'],
            [['title'], 'string', 'max' => 200],
            [['description'], 'string'],
            [['backend_type'], 'string', 'max' => 20],
            [['backend_type'], 'validateBackendAllowed'],
            [['moderator_pw', 'attendee_pw'], 'string', 'max' => 255],
            [['publicJoin', 'joinByPermissions', 'joinCanStart', 'joinCanModerate', 'moderateByPermissions', 'hasWaitingRoom', 'allowRecording', 'muteOnEntry', 'enabled', 'removeImage', 'removePresentation', 'removeCameraBgImage'], 'boolean'],
            [['attendeeRefs', 'moderatorRefs', 'topics', 'backendConfig'], 'safe'],
            [['visibility', 'hidden'], 'integer'],
            [['imageUpload'], 'image', 'extensions' => 'png, jpg, jpeg', 'minWidth' => 200, 'minHeight' => 200, 'skipOnEmpty' => true],
            [['presentationUpload'], 'file', 'extensions' => 'pdf', 'maxSize' => 40 * 1024 * 1024, 'skipOnEmpty' => true],
            [['cameraBgImageUpload'], 'image', 'extensions' => 'png, jpg, jpeg', 'minWidth' => 800, 'minHeight' => 400, 'skipOnEmpty' => true],
        ];
    }

    /**
     * Validates that the selected backend is allowed for this container.
     */
    public function validateBackendAllowed($attribute, $params): void
    {
        if (!BackendRegistry::isAllowedForContainer($this->$attribute, $this->contentContainer)) {
            $this->addError($attribute, Yii::t('SessionsModule.form', 'This video backend is not available.'));
        }
    }

    /**
     * Get available backend options for this form's container.
     * @return array [id => name]
     */
    public function getBackendOptions(): array
    {
        return BackendRegistry::getBackendOptionsForContainer($this->contentContainer);
    }

    /**
     * Get backend-specific config fields for the current backend.
     * @return array Field definitions
     */
    public function getBackendConfigFields(): array
    {
        $backend = BackendRegistry::get($this->backend_type);
        if ($backend) {
            return $backend->getSessionConfigFields();
        }
        return [];
    }

    /**
     * Get backend-specific config fields for all allowed backends.
     * Used for rendering all fields in the form (hidden until backend is selected).
     * @return array [backend_id => [fields...]]
     */
    public function getAllBackendConfigFields(): array
    {
        $result = [];
        $backends = BackendRegistry::getAllowedForContainer($this->contentContainer);
        foreach ($backends as $id => $backend) {
            $result[$id] = $backend->getSessionConfigFields();
        }
        return $result;
    }

    public function attributeLabels(): array
    {
        return [
            'name' => Yii::t('SessionsModule.form', 'URL Slug'),
            'title' => Yii::t('SessionsModule.form', 'Title'),
            'description' => Yii::t('SessionsModule.form', 'Description'),
            'backend_type' => Yii::t('SessionsModule.form', 'Video Backend'),
            'publicJoin' => Yii::t('SessionsModule.form', 'Allow public/guest join'),
            'joinByPermissions' => Yii::t('SessionsModule.form', 'Join by HumHub permissions'),
            'joinCanStart' => Yii::t('SessionsModule.form', 'Participants can start'),
            'joinCanModerate' => Yii::t('SessionsModule.form', 'Participants are moderators'),
            'moderateByPermissions' => Yii::t('SessionsModule.form', 'Moderate by HumHub permissions'),
            'hasWaitingRoom' => Yii::t('SessionsModule.form', 'Enable waiting room'),
            'allowRecording' => Yii::t('SessionsModule.form', 'Allow recording'),
            'muteOnEntry' => Yii::t('SessionsModule.form', 'Mute on entry'),
            'enabled' => Yii::t('SessionsModule.form', 'Enabled'),
            'topics' => Yii::t('SessionsModule.form', 'Topics'),
        ];
    }

    /**
     * @inheritdoc
     * Load form data and handle file uploads
     */
    public function load($data, $formName = null): bool
    {
        $result = parent::load($data, $formName);

        // Handle file uploads
        $imageUpload = UploadedFile::getInstance($this, 'imageUpload');
        if ($imageUpload) {
            $this->imageUpload = $imageUpload;
        }

        $presentationUpload = UploadedFile::getInstance($this, 'presentationUpload');
        if ($presentationUpload) {
            $this->presentationUpload = $presentationUpload;
        }

        $cameraBgUpload = UploadedFile::getInstance($this, 'cameraBgImageUpload');
        if ($cameraBgUpload) {
            $this->cameraBgImageUpload = $cameraBgUpload;
        }

        return $result;
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $isNew = $this->record === null;

        if ($isNew) {
            $this->record = new Session();
            $this->record->uuid = UUID::v4();
            $this->record->creator_user_id = $this->creatorId;
        }

        // Only set container if provided (allows global sessions without container)
        if ($this->contentContainer !== null) {
            $this->record->content->container = $this->contentContainer;
        }

        // Map form to model
        $this->record->backend_type = $this->backend_type;
        $this->record->name = $this->name ?: Inflector::slug($this->title ?: 'session');
        $this->record->title = $this->title;
        $this->record->description = $this->description;
        $this->record->moderator_pw = $this->moderator_pw;
        $this->record->attendee_pw = $this->attendee_pw;
        $this->record->public_join = $this->publicJoin;
        $this->record->join_can_start = $this->joinCanStart;
        $this->record->join_can_moderate = $this->joinCanModerate;
        $this->record->has_waitingroom = $this->hasWaitingRoom;
        $this->record->allow_recording = $this->allowRecording;
        $this->record->mute_on_entry = $this->muteOnEntry;
        $this->record->enabled = $this->enabled;
        $this->record->image_file_id = $this->image_file_id;
        $this->record->presentation_file_id = $this->presentation_file_id;
        $this->record->camera_bg_image_file_id = $this->camera_bg_image_file_id;
        $this->record->setBackendConfig($this->backendConfig);

        // Set content attributes
        if ($this->visibility !== null) {
            $this->record->content->visibility = $this->visibility;
        }
        if ($this->hidden !== null) {
            $this->record->content->hidden = $this->hidden;
        }

        if (!$this->record->save()) {
            $this->addErrors($this->record->getErrors());
            return false;
        }

        $this->id = $this->record->id;

        // Handle file uploads after save (needs session ID)
        $this->saveFileUploads();

        // Re-save if file IDs were updated
        if ($this->record->isAttributeChanged('image_file_id') ||
            $this->record->isAttributeChanged('presentation_file_id') ||
            $this->record->isAttributeChanged('presentation_preview_file_id') ||
            $this->record->isAttributeChanged('camera_bg_image_file_id')) {
            $this->record->save(false);
        }

        // Save user assignments
        $this->saveUserAssignments();

        // Save topics
        $this->saveTopics();

        return true;
    }

    private function saveUserAssignments(): void
    {
        // Moderators: always clear first, then re-create if manual selection
        SessionUser::deleteAll(['session_id' => $this->record->id, 'role' => 'moderator']);
        if (!$this->moderateByPermissions) {
            $moderatorUsers = User::find()
                ->where(['IN', 'guid', (array) $this->moderatorRefs])
                ->all();
            foreach ($moderatorUsers as $user) {
                $this->addUserAssignment($user, 'moderator');
            }
        }

        // Attendees: always clear first, then re-create if manual selection
        SessionUser::deleteAll(['session_id' => $this->record->id, 'role' => 'attendee']);
        if (!$this->joinByPermissions) {
            $attendeeUsers = User::find()
                ->where(['IN', 'guid', (array) $this->attendeeRefs])
                ->andWhere(['NOT IN', 'guid', (array) $this->moderatorRefs])
                ->all();
            foreach ($attendeeUsers as $user) {
                $this->addUserAssignment($user, 'attendee');
            }
        }
    }

    private function addUserAssignment(User $user, string $role): void
    {
        $su = new SessionUser();
        $su->session_id = $this->record->id;
        $su->user_id = $user->id;
        $su->role = $role;
        $su->can_start = $role === 'moderator';
        $su->can_join = true;
        $su->created_at = time();
        $su->save();
    }

    private function saveTopics(): void
    {
        // Only call Topic::attach if there are topics to attach
        // HumHub's Topic::attach checks container permissions BEFORE checking if topics is empty,
        // which causes a crash for global content (no container). By checking here first, we avoid that bug.
        if ($this->record->content && !empty($this->topics)) {
            Topic::attach($this->record->content, $this->topics);
        }
    }

    // ========== File Upload Handling ==========

    /**
     * Save uploaded files and handle removals
     */
    private function saveFileUploads(): void
    {
        // Session image
        if ($this->removeImage && $this->record->image_file_id > 0) {
            $this->deleteFileRef('image_file_id');
        } elseif ($this->imageUpload instanceof UploadedFile) {
            $this->saveSessionImage();
        }

        // Presentation (BBB only)
        if ($this->removePresentation && $this->record->presentation_file_id > 0) {
            $this->deleteFileRef('presentation_file_id');
            $this->deleteFileRef('presentation_preview_file_id');
        } elseif ($this->presentationUpload instanceof UploadedFile) {
            $this->savePresentation();
        }

        // Camera background image (BBB only)
        if ($this->removeCameraBgImage && $this->record->camera_bg_image_file_id > 0) {
            $this->deleteFileRef('camera_bg_image_file_id');
        } elseif ($this->cameraBgImageUpload instanceof UploadedFile) {
            $this->saveCameraBgImage();
        }
    }

    /**
     * Deletes a file reference from the session and removes the file record.
     */
    private function deleteFileRef(string $attribute): void
    {
        $fileId = $this->record->$attribute;
        if ($fileId > 0) {
            $file = File::findOne($fileId);
            if ($file) {
                $file->delete();
            }
            $this->record->$attribute = null;
        }
    }

    /**
     * Save session image upload
     */
    private function saveSessionImage(): bool
    {
        $isEdit = $this->record->image_file_id > 0;
        $file = $isEdit ? $this->record->getImageFile() : new File();

        $file->file_name = $this->imageUpload->baseName . '.' . $this->imageUpload->extension;
        $file->mime_type = $this->imageUpload->type;
        $file->size = $this->imageUpload->size;
        $file->object_id = $this->record->id;

        if (!$file->save()) {
            Yii::error("Could not save session image file record.", 'sessions');
            return false;
        }

        $content = file_get_contents($this->imageUpload->tempName);
        if ($content === false) {
            Yii::error("Could not read session image file.", 'sessions');
            return false;
        }

        $file->setStoredFileContent($content);

        if (!$isEdit) {
            $this->record->image_file_id = $file->id;
            $this->record->fileManager->attach($file->guid);
        }

        return true;
    }

    /**
     * Save presentation upload (PDF)
     */
    private function savePresentation(): bool
    {
        $isEdit = $this->record->presentation_file_id > 0;
        $file = $isEdit ? $this->record->getPresentationFile() : new File();

        $file->file_name = $this->presentationUpload->baseName . '.' . $this->presentationUpload->extension;
        $file->mime_type = $this->presentationUpload->type;
        $file->size = $this->presentationUpload->size;
        $file->object_id = $this->record->id;

        if (!$file->save()) {
            Yii::error("Could not save presentation file record.", 'sessions');
            return false;
        }

        $content = file_get_contents($this->presentationUpload->tempName);
        if ($content === false) {
            Yii::error("Could not read presentation file.", 'sessions');
            return false;
        }

        $file->setStoredFileContent($content);

        if (!$isEdit) {
            $this->record->presentation_file_id = $file->id;
            $this->record->fileManager->attach($file->guid);
        }

        // Generate preview image from first PDF page (if Imagick available)
        $this->savePresentationPreview($this->presentationUpload->tempName);

        return true;
    }

    /**
     * Save presentation preview image from PDF first page
     */
    private function savePresentationPreview(string $pdfPath): bool
    {
        if (!extension_loaded('imagick')) {
            return false;
        }

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(150, 150);
            $imagick->readImage($pdfPath . '[0]'); // First page only
            $imagick->setImageFormat('png');

            $previewPath = $pdfPath . '_preview.png';
            $imagick->writeImage($previewPath);
            $imagick->clear();
            $imagick->destroy();

            $isEdit = $this->record->presentation_preview_file_id > 0;
            $file = $isEdit ? $this->record->getPresentationPreviewImageFile() : new File();

            $file->file_name = $this->presentationUpload->baseName . '_preview.png';
            $file->mime_type = 'image/png';
            $file->size = filesize($previewPath);
            $file->object_id = $this->record->id;

            if (!$file->save()) {
                Yii::error("Could not save presentation preview file record.", 'sessions');
                return false;
            }

            $file->setStoredFileContent(file_get_contents($previewPath));

            if (!$isEdit) {
                $this->record->presentation_preview_file_id = $file->id;
                $this->record->fileManager->attach($file->guid);
            }

            @unlink($previewPath);
            return true;
        } catch (\Exception $e) {
            Yii::error("Could not generate presentation preview: " . $e->getMessage(), 'sessions');
            return false;
        }
    }

    /**
     * Save camera background image upload
     */
    private function saveCameraBgImage(): bool
    {
        $isEdit = $this->record->camera_bg_image_file_id > 0;
        $file = $isEdit ? $this->record->getCameraBgImageFile() : new File();

        $file->file_name = $this->cameraBgImageUpload->baseName . '.' . $this->cameraBgImageUpload->extension;
        $file->mime_type = $this->cameraBgImageUpload->type;
        $file->size = $this->cameraBgImageUpload->size;
        $file->object_id = $this->record->id;

        if (!$file->save()) {
            Yii::error("Could not save camera background image file record.", 'sessions');
            return false;
        }

        $content = file_get_contents($this->cameraBgImageUpload->tempName);
        if ($content === false) {
            Yii::error("Could not read camera background image file.", 'sessions');
            return false;
        }

        $file->setStoredFileContent($content);

        if (!$isEdit) {
            $this->record->camera_bg_image_file_id = $file->id;
            $this->record->fileManager->attach($file->guid);
        }

        return true;
    }

    // ========== File Getters for Preview ==========

    /**
     * Get session image file for preview
     */
    public function getImageFile(): ?File
    {
        if ($this->record && $this->record->image_file_id) {
            return $this->record->getImageFile();
        }
        return null;
    }

    /**
     * Get presentation file for preview
     */
    public function getPresentationFile(): ?File
    {
        if ($this->record && $this->record->presentation_file_id) {
            return $this->record->getPresentationFile();
        }
        return null;
    }

    /**
     * Get presentation preview image for preview
     */
    public function getPresentationPreviewImage(): ?File
    {
        if ($this->record && $this->record->presentation_preview_file_id) {
            return $this->record->getPresentationPreviewImageFile();
        }
        return null;
    }

    /**
     * Get camera background image file for preview
     */
    public function getCameraBgImageFile(): ?File
    {
        if ($this->record && $this->record->camera_bg_image_file_id) {
            return $this->record->getCameraBgImageFile();
        }
        return null;
    }

    /**
     * Get the underlying session record
     */
    public function getRecord(): ?Session
    {
        return $this->record;
    }
}
