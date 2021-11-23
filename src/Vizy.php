<?php
namespace verbb\vizy;

use verbb\vizy\base\PluginTrait;
use verbb\vizy\base\Routes;
use verbb\vizy\fields\VizyField;
use verbb\vizy\gql\interfaces\VizyNodeInterface;
use verbb\vizy\gql\interfaces\VizyBlockInterface;
use verbb\vizy\integrations\feedme\fields\Vizy as FeedMeVizyField;
use verbb\vizy\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\helpers\UrlHelper;
use craft\services\Fields;
use craft\services\Gql;
use craft\services\Matrix;

use yii\base\Event;

use verbb\supertable\services\SuperTableService;

use craft\feedme\events\RegisterFeedMeFieldsEvent;
use craft\feedme\services\Fields as FeedMeFields;

class Vizy extends Plugin
{
    // Public Properties
    // =========================================================================

    public $schemaVersion = '0.9.0';
    public $hasCpSettings = true;
    public $hasCpSection = false;


    // Traits
    // =========================================================================

    use PluginTrait;
    use Routes;


    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        $this->_setPluginComponents();
        $this->_setLogging();
        $this->_registerCpRoutes();
        $this->_registerFieldTypes();
        $this->_registerProjectConfigEventListeners();
        $this->_registerGraphQl();
        $this->_registerThirdPartyEventListeners();
    }

    public function getSettingsResponse()
    {
        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('vizy/settings'));
    }

    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }


    // Private Methods
    // =========================================================================

    private function _registerFieldTypes()
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = VizyField::class;
        });
    }

    private function _registerProjectConfigEventListeners()
    {
        Craft::$app->projectConfig
            ->onAdd(Fields::CONFIG_FIELDS_KEY . '.{uid}', [$this->getService(), 'handleChangedField'])
            ->onUpdate(Fields::CONFIG_FIELDS_KEY . '.{uid}', [$this->getService(), 'handleChangedField'])
            ->onRemove(Fields::CONFIG_FIELDS_KEY . '.{uid}', [$this->getService(), 'handleDeletedField']);

        // Special case for some fields like Matrix, that don't emit the change event for nested fields.
        Craft::$app->projectConfig
            ->onAdd(Matrix::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleChangedBlockType'])
            ->onUpdate(Matrix::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleChangedBlockType'])
            ->onRemove(Matrix::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleDeletedBlockType']);

        if (class_exists(SuperTableService::class)) {
            Craft::$app->projectConfig
                ->onAdd(SuperTableService::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleChangedBlockType'])
                ->onUpdate(SuperTableService::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleChangedBlockType'])
                ->onRemove(SuperTableService::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleDeletedBlockType']);
        }
    }
    
    private function _registerGraphQl()
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, function(RegisterGqlTypesEvent $event) {
            $event->types[] = VizyNodeInterface::class;
            $event->types[] = VizyBlockInterface::class;
        });
    }

    private function _registerThirdPartyEventListeners()
    {
        if (class_exists(FeedMeFields::class)) {
            Event::on(FeedMeFields::class, FeedMeFields::EVENT_REGISTER_FEED_ME_FIELDS, function(RegisterFeedMeFieldsEvent $event) {
                $event->fields[] = FeedMeVizyField::class;
            });
        }
    }
}
