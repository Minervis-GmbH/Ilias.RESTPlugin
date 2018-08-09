<?php

require_once(dirname(__DIR__) . '/models/EBookModel.php');
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/eBook/classes/class.ileBookAccessLog.php");

use RESTController\extensions\eBook\v2\models\EBookModel;
use RESTController\extensions\eBook\v2\models\ErrorMessage;
use RESTController\extensions\eBook\v2\models\NoAccessException;
use RESTController\extensions\eBook\v2\models\NoFileException;
use RESTController\libs\RESTAuth;
use RESTController\libs\RESTilias;
use RESTController\RESTController;
use SRAG\Plugin\eBook\Container\EBookPluginContainer;
use SRAG\Plugin\eBook\Security\Exception\AccessViolationException;
use SRAG\Plugin\eBook\Synchronization\Service\SynchronizationManager;
use SRAG\Plugin\eBook\Synchronization\Service\SynchronizationMapper;

/** @var $app RESTController */

$app->group('/v2/ebook', function () use ($app) {

	$app->post('/sync', RESTAuth::checkAccess(RESTAuth::TOKEN), function() use ($app) {

		try {
			$accessToken = $app->request()->getToken();
			$userId = $accessToken->getUserId();

			RESTilias::loadIlUser();
			RESTilias::initAccessHandling();

			/**
			 * @var  array $body
			 */
			$body = $app->request()->getBody();

			$mapper = new SynchronizationMapper();
			$model = $mapper->fromJson($body);
			$synchronization = $mapper->fromModel($model, $userId);

			/**
			 * @var SynchronizationManager $synchManager
			 */
			$synchManager = EBookPluginContainer::resolve(SynchronizationManager::class);
			$finishedSync = $synchManager->synchronize($synchronization);
			$model = $mapper->toModel($finishedSync);

			if(json_last_error() === JSON_ERROR_NONE) {
				$app->response()->setBody(json_encode($model));
				$app->response()->setStatus(200);
				return;
			}
		}
		catch (AccessViolationException $exception) {
			require_once __DIR__ . '/../models/ErrorMessage.php';
			$app->response()->setBody(json_encode(new ErrorMessage('Access violation one or more books are not accessible by the user.')));
			$app->response()->setStatus(403);
			return;
		}

		$app->response()->setBody('Server was unable to calculate the sync response.');
		$app->response()->setStatus(500);
	});

	/**
	 * GET encoded file binary
	 */
	$app->get('/:refId/file', RESTAuth::checkAccess(RESTAuth::TOKEN), function($ref_id) use ($app) {
		$accessToken = $app->request->getToken();
		$model = new EBookModel();
		$userId = $accessToken->getUserId();

		try {

			$filePath = $model->getFilePathByRefId($userId, $ref_id);

			/**
			 * @var $ilClientIniFile ilIniFile
			 */
			require_once('./Services/FileDelivery/classes/class.ilFileDelivery.php');
			$ilFileDelivery = new \ilFileDelivery($filePath);
			$ilFileDelivery->setMimeType('application/pdf');
			$ilFileDelivery->deliver();

		} catch (NoFileException $e) {
			$app->halt(404, "No file uploaded yet.");
		} catch (NoAccessException $e) {
			$app->halt(401, "No access.");
		}
	});

	/**
	 * GET key
	 */
	$app->post('/:refId/key', RESTAuth::checkAccess(RESTAuth::TOKEN), function($ref_id) use ($app) {
		$accessToken = $app->request()->getToken();
		$model = new EBookModel();
		$userId = $accessToken->getUserId();

		try {
			$key = $model->getKeyByRefId($userId, $ref_id);
			$iv = $model->getIVByRefId($userId, $ref_id);
			$remote_address = $_SERVER['REMOTE_ADDR'];
			$forwarded_for = $_SERVER['HTTP_X_FORWARDED_FOR'];
			$hardware_id = $app->request()->getParameter('hardware_id');
			$access = new \ileBookAccessLog();
			$access->setUserId($userId);
			$access->setEbookId($ref_id);
			$access->setRemoteAddress($remote_address);
			$access->setXForwardedFor($forwarded_for);
			$access->setHardwareId($hardware_id);
			$access->updateTimestamp();
			$access->setAction(\ileBookAccessLog::ACTION_DOWNLOAD_TOKEN);
			$access->create();
			$access->triggerCheck();

			$app->response->body(json_encode([
				"key" => $key,
				"iv" => $iv
			]));
		} catch (NoFileException $e) {
			$app->halt(404, "No file uploaded yet.");
		} catch (NoAccessException $e) {
			$app->halt(401, "No access.");
		}

	});
});