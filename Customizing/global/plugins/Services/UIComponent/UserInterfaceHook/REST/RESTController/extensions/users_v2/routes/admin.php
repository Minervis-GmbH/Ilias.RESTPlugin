<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer and T.Hufschmidt <(schaefer|hufschmidt)@hrz.uni-marburg.de>
 * Since 2014
 */
namespace RESTController\extensions\users_v2;


// This allows us to use shorter names instead of full namespace quantifier
// Requires: $app to be \RESTController\RESTController::getInstance();
use \RESTController\libs          as Libs;
use \RESTController\libs\RESTAuth as RESTAuth;


// Group implemented routes into common group
//  These route are more administative
$app->group('/v2/users', function () use ($app) {
  /**
   * Route: [GET] /account/:userId
   *  Returns information about an ILIAS user object, such as login, first-, lastname, etc.
   *  Access-Token needs to have SYSTEM role (for global accounts) or be CATEGORY-ADMIN in
   *  category given by ref_id parameter (for local accounts).
   *
   * Parameters:
   *  userId - <Int> ILIAS internal user-id of user to return information for
   *  ref_id - <Int> [OPTIONAL] ILIAS internal ref-id of category in which local user-account given by <userId> exists
   *
   * Returns:
   *  userId - <Int> ILIAS internal user-id of user to update user-data for
   *  login - <Int> User login
   *  auth_mode - <String> Authentication-Mode for user (@See ilAuthUtils::_getAuthModeName(...))
   *  time_limit_owner <Int> Reference-ID of owning ILIAS object (eg. refId of category or USER_FOLDER_ID for global accounts)
   *  owner - <Int> ILIAS user-id of user who created this account
   *  owner_login - <String> ILIAS login of user who created this account
   *  client_ip - <String> Restrict user to given ip
   *  active - <Bool> Active or deactive user account
   *  time_limit_from - <String/Int> Set time limit from-which user should be able to use account (ISO 6801)
   *  time_limit_until - <String/Int> Set time limit until-which user should be able to use account (ISO 6801)
   *  time_limit_unlimited - <Bool> Set account to unlimited (otherwise time_limit_from & time_limit_until are active)
   *  interests_general - <Array<String>> General interrest fields of user
   *  interests_help_offered - <Array<String>> Help offered fields of user
   *  interests_help_looking - <Array<String>> Help looking fields of user
   *  latitude - <Number> GPS-Location of user, latitude
   *  longitude - <Number> GPS-Location of user, longitude
   *  loc_zoom - <Int> Default Zoom-Level for maps
   *  udf - <Array<Mixed> List of user-defined key => value pairs (@See: Administration -> User Administration -> User-Defined Fields)
   *  language - <String> Current language of user (@See ilLanguage->getInstalledLanguages())
   *  birthday - <String> The users birthday (Only date-section of ISO 6801)
   *  gender - <m/f> Gender of user (can also be Male/Female)
   *  institution - <String> Institution of user
   *  department - <String> Department of user
   *  street - <String> Street of user
   *  city - <String> City of user
   *  zipcode - <String> City-Zipcode of user
   *  country - <String> Country of user (Free-text)
   *  sel_country - <String> Country of user (Via selection) (@See ilCountry::getCountryCodes())
   *  phone_office - <String> Office phone-number of user
   *  phone_home - <String> Home phone-number of user
   *  phone_mobile - <String> Mobile phone-number of user
   *  fax - <String> FAX-Number of user
   *  matriculation - <String> Matriculation (ID) of user
   *  hobby - <String> Hobby-text of user
   *  referral_comment - <String> Referral comment of user
   *  delicious - <String> Delicious account of user
   *  email - <String> Email-Address of user
   *  im_icq - <String> Instant-Messenging ICQ-Account of user
   *  im_yahoo - <String> Instant-Messenging Yahoo-Account of user
   *  im_msn - <String> Instant-Messenging MSN-Account of user
   *  im_aim - <String> Instant-Messenging AIM-Account of user
   *  im_skype - <String> Instant-Messenging Skype-Account of user
   *  im_jabber - <String> Instant-Messenging Jabber-Account of user
   *  im_voip - <String> Instant-Messenging VOIP-Number of user
   *  title - <String> Title of user
   *  firstname - <String> Firstname of user
   *  lastname - <String> Lastname of user
   *  hits_per_page - <Int> Hits-Per-Page setting of user
   *  show_users_online - <Bool> Show-Users-Online setting of user
   *  hide_own_online_status - <Bool> Hide-Online-Status setting of user
   *  skin_style - <String> Skin & Style setting of user, needs to be in Format 'SKIN:STYLE' (colon-delimited)
   *  session_reminder_enabled - <Bool> Session-Reminder setting of user
   *  passwd_change_demanded - <Bool> Wether a password change is required for this user (eg. reached maximum age)
   *  passwd_expired - <Bool> Wether password was expired  (eg. reached maximum age, ISO 6801)
   *  passwd_enc_type - <md5/bcrypt> The encoding-type for this users password
   *  passwd_timestamp - <String> Timestamp of last password change for this user (ISO 6801)
   *  agree_date - <String> Date of user agreement signing (ISO 6801)
   *  create_date - <String> Initial creation-time of this user account (ISO 6801)
   *  last_login - <String> Timestamp of last login (ISO 6801)
   *  approve_date - <String> Timestamp of user activation time (if active, ISO 6801)
   *  inactivation_date - <String> Timestamp of user inactivation time (if inactive, ISO 6801)
   *  time_limit_message - <Bool> Wether an account-expiration email was send
   *  profile_incomplete - <Bool> Tells wether ILIAS deems this user account data-complete (no missing required fields)
   *  ext_account - <String> [Optional] External account name of user
   *  disk_quota - <Number> [Optional] Global disk-quota for user (courses, groups, files, etc)
   *  wsp_disk_quota - <Number> [Optional] Personal workspace disk-quota for user
   *  userfile - <String> [Optional] BASE64-Encoded JPG image (Example: data:image/jpeg;base64,<BASE-64-PAYLOAD>, without <>)
   *  roles - <Array<Int>> [Optional] A list of ilias roles (numeric-ids) of roles to assign the user to
   *
   * Throws:
   *  <DocIt!!!>
   */
  $app->get('/account/:userId', RESTAuth::checkAccess(RESTAuth::PERMISSION), function ($userId) use ($app) {
    // Catch and handle exceptions if possible
    try {
      // Fetch additional input parameters
      $request  = $app->request();
      $refId    = $request->getParameter('ref_id', AdminModel::USER_FOLDER_ID);

      // Initialize RBAC (user is fetched from access-token)
      Libs\RESTilias::loadIlUser();
      Libs\RESTilias::initAccessHandling();

      // Check input, create/update user and assign roles
      $userData = AdminModel::FetchUserData($userId, $refId);

      // Return updated user data
      $app->success($userData);
    }
    // Catch any exception
    // TODO: Send different return code based on exception class!!!
    catch (Libs\LibException $e) {
      $app->halt(500, $e->getFormatedMessage(), $e->getRESTCode());
    }
  });


  /**
   * Route: [PUT] /account/:userId
   *  Updates user-data of an existing ILIAS user object, eg. changing password, first- or lastname.
   *  Access-Token needs to have SYSTEM role (for global accounts) or be CATEGORY-ADMIN in
   *  category given by ref_id parameter (for local accounts).
   *
   * Parameters:
   *  userId - <Int> ILIAS internal user-id of user to update user-data for
   *  login - <Int> [Optional] User login
   *  auth_mode - <String> [Optional] Authentication-Mode for user (@See ilAuthUtils::_getAuthModeName(...))
   *  client_ip - <String> [Optional] Restrict user to given ip
   *  active - <Bool> [Optional] Active or deactive user account
   *  time_limit_from - <String/Int> [Optional] Set time limit from-which user should be able to use account (Unix-Time or ISO 6801)
   *  time_limit_until - <String/Int> [Optional] Set time limit until-which user should be able to use account (Unix-Time or ISO 6801)
   *  time_limit_unlimited - <Bool> [Optional] Set account to unlimited (otherwise time_limit_from & time_limit_until are active)
   *  interests_general - <Array<String>> [Optional] General interrest fields of user
   *  interests_help_offered - <Array<String>> [Optional] Help offered fields of user
   *  interests_help_looking - <Array<String>> [Optional] Help looking fields of user
   *  latitude - <Number> [Optional] GPS-Location of user, latitude
   *  longitude - <Number> [Optional] GPS-Location of user, longitude
   *  loc_zoom - <Int> [Optional] Default Zoom-Level for maps
   *  udf - <Array<Mixed> [Optional] List of user-defined key => value pairs (@See: Administration -> User Administration -> User-Defined Fields)
   *  language - <String> [Optional] Current language of user (@See ilLanguage->getInstalledLanguages())
   *  birthday - <String> [Optional] The users birthday (Only date-section of ISO 6801)
   *  gender - <m/f> [Optional] Gender of user (can also be Male/Female)
   *  institution - <String> [Optional] Institution of user
   *  department - <String> [Optional] Department of user
   *  street - <String> [Optional] Street of user
   *  city - <String> [Optional] City of user
   *  zipcode - <String> [Optional] City-Zipcode of user
   *  country - <String> [Optional] Country of user (Free-text)
   *  sel_country - <String> [Optional] Country of user (Via selection) (@See ilCountry::getCountryCodes())
   *  phone_office - <String> [Optional] Office phone-number of user
   *  phone_home - <String> [Optional] Home phone-number of user
   *  phone_mobile - <String> [Optional] Mobile phone-number of user
   *  fax - <String> [Optional] FAX-Number of user
   *  matriculation - <String> [Optional] Matriculation (ID) of user
   *  hobby - <String> [Optional] Hobby-text of user
   *  referral_comment - <String> [Optional] Referral comment of user
   *  delicious - <String> [Optional] Delicious account of user
   *  email - <String> [Optional] Email-Address of user
   *  im_icq - <String> [Optional] Instant-Messenging ICQ-Account of user
   *  im_yahoo - <String> [Optional] Instant-Messenging Yahoo-Account of user
   *  im_msn - <String> [Optional] Instant-Messenging MSN-Account of user
   *  im_aim - <String> [Optional] Instant-Messenging AIM-Account of user
   *  im_skype - <String> [Optional] Instant-Messenging Skype-Account of user
   *  im_jabber - <String> [Optional] Instant-Messenging Jabber-Account of user
   *  im_voip - <String> [Optional] Instant-Messenging VOIP-Number of user
   *  title - <String> [Optional] Title of user
   *  firstname - <String> [Optional] Firstname of user
   *  lastname - <String> [Optional] Lastname of user
   *  hits_per_page - <Int> [Optional] Hits-Per-Page setting of user
   *  show_users_online - <Bool> [Optional] Show-Users-Online setting of user
   *  hide_own_online_status - <Bool> [Optional] Hide-Online-Status setting of user
   *  skin_style - <String> [Optional] Skin & Style setting of user, needs to be in Format 'SKIN:STYLE' (colon-delimited)
   *  session_reminder_enabled - <Bool> [Optional] Session-Reminder setting of user
   *  passwd - <String> [Optional] Plain-Text password of user
   *  ext_account - <String> [Optional] External account name of user
   *  disk_quota - <Number> [Optional] Global disk-quota for user (courses, groups, files, etc)
   *  wsp_disk_quota - <Number> [Optional] Personal workspace disk-quota for user
   *  userfile - <String> [Optional] BASE64-Encoded JPG image (Example: data:image/jpeg;base64,<BASE-64-PAYLOAD>, without <>)
   *  roles - <Array<Int>> [Optional] A list of ilias roles (numeric-ids) of roles to assign the user to
   *
   * Returns:
   *  On success a cleaned-up list of input-parameters if returned. This does not mean every value was
   *  actually changed, since this depends on ILIAS settings for user-data fields and the access-token user role.
   *  (@See: Administration -> User Administration -> Default Fields / User-Defined Fields)
   *
   * Throws:
   *  <DocIt!!!>
   */
  $app->put('/account/:userId', RESTAuth::checkAccess(RESTAuth::PERMISSION), function ($userId) use ($app) {
    // Catch and handle exceptions if possible
    try {
      // Fetch input parameters
      $request  = $app->request();
      $refId    = $request->getParameter('ref_id', AdminModel::USER_FOLDER_ID);
      $userData = array();
      foreach (AdminModel::fields as $field) {
        $value = $request->getParameter($field);
        if (isset($value))
          $userData[$field] = $value;
      }
      $userData['id'] = $userId;

      // Initialize RBAC (user is fetched from access-token)
      Libs\RESTilias::loadIlUser();
      Libs\RESTilias::initAccessHandling();

      // Check input, create/update user and assign roles
      $cleanUserData = AdminModel::CheckUserData($userData, AdminModel::MODE_UPDATE, $refId);
      $result        = AdminModel::StoreUserData($cleanUserData, AdminModel::MODE_UPDATE, $refId);

      // Return updated user data
      $app->success($cleanUserData);
    }
    // Catch any exception
    // TODO: Send different return code based on exception class!!!
    catch (Libs\LibException $e) {
      $app->halt(500, $e->getFormatedMessage(), $e->getRESTCode());
    }
  });


  /**
   * Route: [POST] /account
   *  This route creates a new ILIAS user account with the given user-data.
   *  Access-Token needs to have SYSTEM role (for global accounts) or be CATEGORY-ADMIN in
   *  category given by ref_id parameter (for local accounts).
   *  Wether some of the below fields are actually OPTIONAL depends on if the access-token
   *  has the SYSTEM role and how ILIAS is configured in regards to required user-data fields.
   *  (@See Administration -> User Administration -> Default Fields / User-Defined Fields)
   *
   * Parameters:
   *  login - <Int> User login
   *  auth_mode - <String> [Optional] Authentication-Mode for user (@See ilAuthUtils::_getAuthModeName(...))
   *  client_ip - <String> [Optional] Restrict user to given ip
   *  active - <Bool> [Optional] Active or deactive user account
   *  time_limit_from - <String/Int> [Optional] Set time limit from-which user should be able to use account (Unix-Time or ISO 6801)
   *  time_limit_until - <String/Int> [Optional] Set time limit until-which user should be able to use account (Unix-Time or ISO 6801)
   *  time_limit_unlimited - <Bool> [Optional] Set account to unlimited (otherwise time_limit_from & time_limit_until are active)
   *  interests_general - <Array<String>> [Optional] General interrest fields of user
   *  interests_help_offered - <Array<String>> [Optional] Help offered fields of user
   *  interests_help_looking - <Array<String>> [Optional] Help looking fields of user
   *  latitude - <Number> [Optional] GPS-Location of user, latitude
   *  longitude - <Number> [Optional] GPS-Location of user, longitude
   *  loc_zoom - <Int> [Optional] Default Zoom-Level for maps
   *  udf - <Array<Mixed> [Optional] List of user-defined key => value pairs (@See: Administration -> User Administration -> User-Defined Fields)
   *  language - <String> [Optional] Current language of user (@See ilLanguage->getInstalledLanguages())
   *  birthday - <String> [Optional] The users birthday (Only date-section of ISO 6801)
   *  gender - <m/f> Gender of user (can also be Male/Female)
   *  institution - <String> [Optional] Institution of user
   *  department - <String> [Optional] Department of user
   *  street - <String> [Optional] Street of user
   *  city - <String> [Optional] City of user
   *  zipcode - <String> [Optional] City-Zipcode of user
   *  country - <String> [Optional] Country of user (Free-text)
   *  sel_country - <String> [Optional] Country of user (Via selection) (@See ilCountry::getCountryCodes())
   *  phone_office - <String> [Optional] Office phone-number of user
   *  phone_home - <String> [Optional] Home phone-number of user
   *  phone_mobile - <String> [Optional] Mobile phone-number of user
   *  fax - <String> [Optional] FAX-Number of user
   *  matriculation - <String> [Optional] Matriculation (ID) of user
   *  hobby - <String> [Optional] Hobby-text of user
   *  referral_comment - <String> [Optional] Referral comment of user
   *  delicious - <String> [Optional] Delicious account of user
   *  email - <String> Email-Address of user
   *  im_icq - <String> [Optional] Instant-Messenging ICQ-Account of user
   *  im_yahoo - <String> [Optional] Instant-Messenging Yahoo-Account of user
   *  im_msn - <String> [Optional] Instant-Messenging MSN-Account of user
   *  im_aim - <String> [Optional] Instant-Messenging AIM-Account of user
   *  im_skype - <String> [Optional] Instant-Messenging Skype-Account of user
   *  im_jabber - <String> [Optional] Instant-Messenging Jabber-Account of user
   *  im_voip - <String> [Optional] Instant-Messenging VOIP-Number of user
   *  title - <String> [Optional] Title of user
   *  firstname - <String> Firstname of user
   *  lastname - <String> Lastname of user
   *  hits_per_page - <Int> [Optional] Hits-Per-Page setting of user
   *  show_users_online - <Bool> [Optional] Show-Users-Online setting of user
   *  hide_own_online_status - <Bool> [Optional] Hide-Online-Status setting of user
   *  skin_style - <String> [Optional] Skin & Style setting of user, needs to be in Format 'SKIN:STYLE' (colon-delimited)
   *  session_reminder_enabled - <Bool> [Optional] Session-Reminder setting of user
   *  passwd - <String> Plain-Text password of user
   *  ext_account - <String> [Optional] External account name of user
   *  disk_quota - <Number> [Optional] Global disk-quota for user (courses, groups, files, etc)
   *  wsp_disk_quota - <Number> [Optional] Personal workspace disk-quota for user
   *  userfile - <String> [Optional] BASE64-Encoded JPG image (Example: data:image/jpeg;base64,<BASE-64-PAYLOAD>, without <>)
   *  roles - <Array<Int>> A list of ilias roles (numeric-ids) of roles to assign the user to
   *  send_mail - <Bool> [Optional] Trigger sending user email notification
   *
   * Returns:
   *  On success a cleaned-up list of input-parameters if returned. This does not mean every value was
   *  actually changed, since this depends on ILIAS settings for user-data fields and the access-token user role.
   *  (@See: Administration -> User Administration -> Default Fields / User-Defined Fields)
   *
   * Throws:
   *  <DocIt!!!>
   */
  $app->post('/account', RESTAuth::checkAccess(RESTAuth::PERMISSION), function () use ($app) {
    // Catch and handle exceptions if possible
    try {
      // Fetch input parameters
      $request  = $app->request;
      $refId    = $request->getParameter('ref_id', AdminModel::USER_FOLDER_ID);
      $userData = array();
      foreach (AdminModel::fields as $field) {
        $value = $request->getParameter($field);
        if (isset($value))
          $userData[$field] = $value;
      }

      // Initialize RBAC (user is fetched from access-token)
      Libs\RESTilias::loadIlUser();
      Libs\RESTilias::initAccessHandling();

      // Check input, create/update user and assign roles
      $cleanUserData = AdminModel::CheckUserData($userData, AdminModel::MODE_CREATE, $refId);
      $result        = AdminModel::StoreUserData($cleanUserData, AdminModel::MODE_CREATE, $refId);

      // Return updated user data
      $app->success($cleanUserData);
    }
    // Catch any exception
    // TODO: Send different return code based on exception class!!!
    catch (Libs\LibException $e) {
      $app->halt(500, $e->getFormatedMessage(), $e->getRESTCode());
    }
  });


  /**
   * Todo: Implement a route that can delete an existing ILIAS user
   */
  $app->delete('/account/:userId', RESTAuth::checkAccess(RESTAuth::PERMISSION), function ($userId) use ($app) {
    // Note: Ensure access-token is allowed to delete given user
    $app->halt(501, 'Not yet implemented...');
  });
// End of URI group
});
