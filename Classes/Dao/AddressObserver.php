<?php
namespace CommerceTeam\Commerce\Dao;
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use CommerceTeam\Commerce\Controller\AddressesController;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * For the takeaday feuser extension
 * The class satisfies the observer design pattern.
 * The method update() from this class is called as static by "hooksHandler"
 * classes
 * This class handles tt_address updates
 *
 * Class \CommerceTeam\Commerce\Dao\AddressObserver
 *
 * @author 2005-2011 Carsten Lausen <cl@e-netconsulting.de>
 */
class AddressObserver {
	/**
	 * Link to observable
	 *
	 * @var object
	 */
	public $observable;

	/**
	 * Constructor
	 * Link observer and observable
	 * Not needed for typo3 hook concept.
	 *
	 * @param object $observable Observed object
	 *
	 * @return self
	 */
	public function __construct(&$observable) {
		$this->observable = $observable;
		$observable->addObserver($this);
	}

	/**
	 * Handle update event.
	 * Is called from observable or hook handlers upon event.
	 * Keep this method static for efficient integration into hookHandlers.
	 * Communicate using push principle to avoid errors.
	 *
	 * @param string $status Status [update,new]
	 * @param string $id Database table id
	 *
	 * @return void
	 */
	public static function update($status, $id) {
		// get complete address object
		/**
		 * Address data access object
		 *
		 * @var AddressDao $addressDao
		 */
		$addressDao = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('CommerceTeam\\Commerce\\Dao\\AddressDao', $id);

		// get feuser id
		$feuserId = $addressDao->get('tx_commerce_fe_user_id');

		if (!empty($feuserId)) {
			// get associated feuser object
			/**
			 * Frontend user data access object
			 *
			 * @var FeuserDao $feuserDao
			 */
			$feuserDao = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('CommerceTeam\\Commerce\\Dao\\FeuserDao', $feuserId);

			// update feuser object
			/**
			 * Frontend user address field mapper
			 *
			 * @var FeuserAddressFieldmapper $fieldMapper
			 */
			$fieldMapper = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('CommerceTeam\\Commerce\\Dao\\FeuserAddressFieldmapper');
			$fieldMapper->mapAddressToFeuser($addressDao, $feuserDao);

			// set main address id in feuser
			$feuserDao->set('tx_commerce_tt_address_id', $id);
			$feuserDao->save();
		}
	}

	/**
	 * Check if address may get deleted
	 *
	 * @param int $uid Uid
	 * @param AddressesController|DataHandler $parentObject Parent object
	 *
	 * @return bool|string
	 */
	public static function checkDelete($uid, $parentObject) {
		$dbFields = 'uid';
		$dbTable = 'fe_users';
		$dbWhere = 'tx_commerce_tt_address_id = "' . (int) $uid . '" AND deleted = "0"';

		$database = self::getDatabaseConnection();

		$res = $database->exec_SELECTquery($dbFields, $dbTable, $dbWhere);

		// no errormessage
		$msg = FALSE;
		// check dependencies (selected rows)
		if ($database->sql_num_rows($res) > 0) {
			// errormessage
			if ($parentObject instanceof AddressesController) {
				$msg = $parentObject->pi_getLL('error_deleted_address_is_default');
			}
			if ($parentObject instanceof DataHandler) {
				$msg = self::getLanguageService()->sl(
					'LLL:EXT:commerce/Resources/Private/Language/locallang.xml:error_deleted_address_is_default'
				);
			}
		}

		// free results
		$database->sql_free_result($res);

		return $msg;
	}


	/**
	 * Get database connection
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected static function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Get language service
	 *
	 * @return \TYPO3\CMS\Lang\LanguageService
	 */
	protected static function getLanguageService() {
		return $GLOBALS['LANG'];
	}
}
