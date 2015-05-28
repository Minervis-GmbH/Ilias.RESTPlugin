<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer, S.Schneider and T. Hufschmidt <(schaefer|schneider|hufschmidt)@hrz.uni-marburg.de>
 * 2014-2015
 */
namespace RESTController\extensions\roles_v1;

// This allows us to use shortcuts instead of full quantifier
use \RESTController\libs as Libs;


class RolesModel
{
    public function getAllRoles($req, &$resp){

        global $rbacsystem, $rbacreview;
        // get all roles of system role folder
        // TODO: c/p aus users/bulkImport
        // TODO: do it here or in route?

       // Fetch authorized user
       $auth = new Auth\Util();
       $user = $auth->getAccessToken()->getUserName();

        Libs\RESTLib::setUserContext($user);  // filled by auth middleware
        Libs\RESTLib::initAccessHandling();


        if(!$rbacsystem->checkAccess('read',ROLE_FOLDER_ID))
        {
            $resp->setRESTCode(-100);
            $resp->setMessage("No access to list roles");
            $resp->setHttpStatus(400);
            return;
        }

        $roles = $rbacreview->getAssignableRoles(false, true);
        $num_roles = 0;


        $app = new \Slim\Slim();
        if(count($app->request->params()) != 0){
            foreach($roles as $role) {
                $match = true;
                foreach($app->request->params() as $param=>$value) {
                    if(!isset($role[$param]) || $role[$param] != $value){
                        $match = false;
                        break;
                    }
                }

                if($match == true) {
                    $resp->addData('roles', $role);
                    $num_roles++;
                }

            }
        } else {
            $resp->setData('roles', $roles);
            $num_roles = count($roles);
        }
        $resp->setMessage("$num_roles roles found");
        $resp->setRESTCode(200);
        $resp->setHttpStatus(200);
    }

}

?>