<?php
namespace infoservio\donateelite\migrations;

use Yii;
use Craft;
use craft\db\Migration;

use infoservio\donateelite\DonateElite;

class Install extends Migration
{
    public $driver;

    private $_countryCsvPath = __DIR__ . '/../assets/countries.csv';
    private $_usaStatesCsvPath = __DIR__ . '/../assets/usa-states.csv';

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp()
    {
        $this->createTables();
        // Refresh the db schema caches
        Craft::$app->db->schema->refresh();

        $this->insertMailManagerTemplate();

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown()
    {
//        $this->removeForeignKeys();
//        $this->removeTables();
        return true;
    }

    // Private Methods
    // =========================================================================

    private function createTables()
    {
        if (!$this->tableExists('donations_transaction')) {
            $this->createTable('donations_transaction', [
                'id' => $this->primaryKey(),
                'transactionId' => $this->text(),
                'type' => $this->text()->null(),
                'cardId' => $this->integer(),
                'amount' => $this->float(),
                'status' => $this->string(50),
                'projectId' => $this->integer()->null(),
                'projectName' => $this->string(50)->null(),
                'transactionDetails' => $this->text()->null(),
                'transactionErrors' => $this->text()->null(),
                'transactionErrorMessage' => $this->text()->null(),
                'dateCreated' => $this->date(),
                'dateUpdated' => $this->date(),
                'uid' => $this->text()
            ]);
        }

        if (!$this->tableExists('donations_card')) {
            $this->createTable('donations_card', [
                'id' => $this->primaryKey(),
                'tokenId' => $this->string(36),
                'customerId' => $this->integer(),
                'bin' => $this->string(20),
                'last4' => $this->string(4),
                'cardType' => $this->string(32),
                'expirationDate' => $this->string(20),
                'cardholderName' => $this->string()->null(),
                'customerLocation' => $this->string(2)->null(),
                'dateCreated' => $this->date(),
                'dateUpdated' => $this->date(),
                'uid' => $this->text()
            ]);

            $this->addForeignKey(
                'fk-donations-transaction-card',
                'donations_transaction',
                'cardId',
                'donations_card',
                'id'
            );
        }

        if (!$this->tableExists('donations_customer')) {
            $this->createTable('donations_customer', [
                'id' => $this->primaryKey(),
                'customerId' => $this->string(36),
                'addressId' => $this->integer(),
                'firstName' => $this->string(50),
                'lastName' => $this->string(50),
                'email' => $this->string(50),
                'phone' => $this->string(50),
                'dateCreated' => $this->date(),
                'dateUpdated' => $this->date(),
                'uid' => $this->text()
            ]);

            $this->addForeignKey(
                'fk-donations-card-customer',
                'donations_card',
                'customerId',
                'donations_customer',
                'id'
            );
        }

        if (!$this->tableExists('donations_address')) {
            $this->createTable('donations_address', [
                'id' => $this->primaryKey(),
                'company' => $this->string(50),
                'countryId' => $this->integer(),
                'stateId' => $this->string(50)->null(),
                'city' => $this->string(50),
                'postalCode' => $this->string(),
                'streetAddress' => $this->string(100),
                'extendedAddress' => $this->string(100)->null(),
                'dateCreated' => $this->date(),
                'dateUpdated' => $this->date(),
                'uid' => $this->text()
            ]);

            $this->addForeignKey(
                'fk-donations-customer-address',
                'donations_customer',
                'addressId',
                'donations_address',
                'id'
            );
        }

        if (!$this->tableExists('donations_recurring_payment')) {
            $this->createTable('donations_recurring_payment', [
                'id' => $this->primaryKey(),
                'cardId' => $this->integer(),
                'frequency' => $this->integer(),
                'amount' => $this->integer(),
                'status' => $this->integer(),
                'lastDateDonation' => $this->date(),
                'nextDateDonation' => $this->date(),
                'dateCreated' => $this->date(),
                'dateUpdated' => $this->date(),
                'uid' => $this->text()
            ]);

            $this->addForeignKey(
                'fk-donations-recurring_payment-card',
                'donations_recurring_payment',
                'cardId',
                'donations_card',
                'id'
            );
        }

        if (!$this->tableExists('donations_country')) {
            $this->createTable('donations_country', [
                'id' => $this->primaryKey(),
                'name' => $this->string(100),
                'alpha2' => $this->string(2),
                'alpha3' => $this->string(3),
                'countryCode' => $this->integer(),
                'region' => $this->string(50),
                'subRegion' => $this->string(50),
                'regionCode' => $this->integer(50),
                'subRegionCode' => $this->integer()
            ]);

            $this->addForeignKey(
                'fk-donations-address-country',
                'donations_address',
                'countryId',
                'donations_country',
                'id'
            );

            if ($this->fileExists($this->_countryCsvPath)) {
                $this->insertCountries();
            }
        }

        if (!$this->tableExists('donations_state')) {
            $this->createTable('donations_state', [
                'id' => $this->primaryKey(),
                'name' => $this->string(50),
                'code' => $this->string(2)
            ]);

            if ($this->fileExists($this->_usaStatesCsvPath)) {
                $this->insertUsaStates();
            }
        }

        if (!$this->tableExists('donations_logs')) {
            $this->createTable('donations_logs', [
                'id' => $this->primaryKey(),
                'pid' => $this->integer(),
                'culprit' => $this->integer(),
                'category' => $this->text(),
                'method' => $this->text(),
                'message' => $this->text(),
                'errors' => $this->text(),
                'dateCreated' => $this->date(),
                'dateUpdated' => $this->date(),
                'uid' => $this->text()
            ]);
        }

        if (!$this->tableExists('donations_field')) {
            $this->createTable('donations_field', [
                'id' => $this->primaryKey(),
                'name' => $this->string(255)->unique(),
                'title' => $this->string(255),
                'required' => $this->smallInteger()->defaultValue(1),
                'show' => $this->smallInteger()->defaultValue(1),
                'canHide' => $this->smallInteger()->defaultValue(0),
                'dateCreated' => $this->date(),
                'dateUpdated' => $this->date(),
                'uid' => $this->text()
            ]);

            $this->insertDonationsFieldDefaultValue();
        }

        if (!$this->tableExists('donations_settings')) {
            $this->createTable('donations_settings', [
                'id' => $this->primaryKey(),
                'name' => $this->string(255)->unique(),
                'value' => $this->text(),
                'dateCreated' => $this->date(),
                'dateUpdated' => $this->date(),
                'uid' => $this->text()
            ]);

            $this->insertDonationsSettingsDefaultValue();
        }

        if (!$this->tableExists('donations_step')) {
            $this->createTable('donations_step', [
                'id' => $this->primaryKey(),
                'name' => $this->string(255)->unique(),
                'title' => $this->string(255),
                'order' => $this->smallInteger(),
                'dateCreated' => $this->date(),
                'dateUpdated' => $this->date(),
                'uid' => $this->text()
            ]);

            $this->insertDonationsStepsDefaultValue();
        }
    }

    private function removeForeignKeys()
    {
        $this->dropForeignKey(
            'fk-donations-transaction-card',
            'donations_transaction'
        );

        $this->dropForeignKey(
            'fk-donations-card-customer',
            'donations_card'
        );

        $this->dropForeignKey(
            'fk-donations-recurring_payment-card',
            'donations_recurring_payment'
        );

        $this->dropForeignKey(
            'fk-donations-customer-address',
            'donations_customer'
        );

        $this->dropForeignKey(
            'fk-donations-address-country',
            'donations_address'
        );
    }

    private function removeTables()
    {
        $this->dropTableIfExists('donations_recurring_payment');
        $this->dropTableIfExists('donations_transaction');
        $this->dropTableIfExists('donations_customer');
        $this->dropTableIfExists('donations_address');
        $this->dropTableIfExists('donations_card');
        $this->dropTableIfExists('donations_country');
        $this->dropTableIfExists('donations_state');
        $this->dropTableIfExists('donations_logs');
        $this->dropTableIfExists('donations_field');
        $this->dropTableIfExists('donations_settings');
        $this->dropTableIfExists('donations_step');
    }

    private function insertMailManagerTemplate()
    {
        try {
            $this->insert('mailmanager_template', [
                'name' => 'Success Donation Email',
                'slug' => 'success-donation',
                'subject' => 'Thank you for donation',
                'template' => 'Thank you for donation.'
            ]);
        } catch (\Exception $e) {
            // test
        }
    }

    private function insertCountries()
    {
        $countries = DonateElite::$PLUGIN->csvParser->parseCsvFile($this->_countryCsvPath);
        $country = null;
        foreach($countries as &$country) {
            $this->insert('donations_country', [
                'name' => $country['name'],
                'alpha2' => $country['alpha-2'],
                'alpha3' => $country['alpha-3'],
                'countryCode' => $country['country-code'],
                'region' => $country['region'],
                'subRegion' => $country['sub-region'],
                'regionCode' => $country['region-code'],
                'subRegionCode' => $country['sub-region-code']
            ], false);
        }
        unset($country);
        unset($countries);
    }

    private function insertUsaStates()
    {
        $usaStates = DonateElite::$PLUGIN->csvParser->parseCsvFile($this->_usaStatesCsvPath);
        $state = null;
        foreach($usaStates as &$state) {
            $this->insert('donations_state', [
                'name' => $state['StateName'],
                'code' => $state['StateCode']
            ], false);
        }
        unset($state);
        unset($usaStates);
    }

    private function insertDonationsFieldDefaultValue()
    {
        $this->insert('donations_field', [
            'name' => 'phone',
            'title' => 'Phone',
            'required' => 0
        ]);

        $this->insert('donations_field', [
            'name' => 'company',
            'title' => 'Company',
            'required' => 0,
            'canHide' => 1
        ]);
    }

    private function insertDonationsSettingsDefaultValue()
    {
        $this->insert('donations_settings', [
            'name' => 'successMessage',
            'value' => 'Success Message'
        ]);

        $this->insert('donations_settings', [
            'name' => 'errorMessage',
            'value' => 'Error Message'
        ]);

        $this->insert('donations_settings', [
            'name' => 'color',
            'value' => 'F62F5E'
        ]);
    }

    private function insertDonationsStepsDefaultValue()
    {
        $this->insert('donations_step', [
            'name' => 'payment',
            'title' => 'Payment',
            'order' => 1
        ]);

        $this->insert('donations_step', [
            'name' => 'contact-info',
            'title' => 'Contact Info',
            'order' => 2
        ]);

        $this->insert('donations_step', [
            'name' => 'address',
            'title' => 'Address',
            'order' => 3
        ]);

        $this->insert('donations_step', [
            'name' => 'finish',
            'title' => 'Finish',
            'order' => 4
        ]);
    }

    private function fileExists($path)
    {
        return file_exists($path);
    }

    private function tableExists($table)
    {
        return (Yii::$app->db->schema->getTableSchema($table) !== null);
    }
}

