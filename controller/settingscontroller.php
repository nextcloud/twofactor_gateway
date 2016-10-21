<?php
/**
 * ownCloud - Richdocuments App
 *
 * @author Blagovest Petrov
 * @copyright 2015 Blagovest Petrov <blagovest@petrovs.info>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\TwoFactor_Sms\Controller;

use \OCP\AppFramework\Controller;
use \OCP\IConfig;
use \OCP\IRequest;
use \OCP\IL10N;
use \OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;

//use OCA\Richdocuments\AppConfig;
//use OCA\Richdocuments\Filter;

class SettingsController extends Controller{

	private $userId;
	private $l10n;
	private $appConfig;
  private $appName = 'TwoFactor_Sms'

	public function __construct($appName, IRequest $request, IL10N $l10n, AppConfig $appConfig, $userId){
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->appConfig = $appConfig;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function settingsIndex(){
		return new TemplateResponse(
			$this->appName,
			'personal',
			[ 'phone' => $this->appConfig->getUserValue($this->userId, 'phone') ],
			'blank'
		);
	}

	public function saveSettings($phone){
		if (is_null($phone)){
      $response = array(
        'status' => 'error',
        'data'   => array( 'message' => $this->l10n->t('The phone number value must not be empty!'))
      )
		}
    else {
      $this->appConfig->setUserValue($this->userId, 'phone', $phone);
      $response = array(
        'status' => 'success',
        'data'   => array('message' => $this->l10n->t("Phone number saved successfully."))
      );
    }
		return $response;
	}

}
