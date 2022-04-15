<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Controllers;

use \DFTDAPI\Actions\UserActions as UserActions;
use \DFTDAPI\Models\RefreshTokenDAO;
use DFTDAPI_JWT;
use User;
use Item;
use ItemResource;
use UserEmailTmp;
use Page;
use Params;

class UserController extends Controller {
    const ACCESS_TOKEN_DURATION = 3600; // 60 minutes
    const REFRESH_TOKEN_DURATION = 30 * 86400; // 30 days
    const REFRESH_TOKEN_DURATION_REMEMBER_ME = 365 * 86400; // 365 days

    public $userDAO;
    public $refreshTokenDAO;

    public function __construct() {
        parent::__construct();
        $this->userDAO = User::newInstance();
        $this->refreshTokenDAO = RefreshTokenDAO::newInstance();
    }

    public function login() {
        if(osc_is_web_user_logged_in()) $this->abort(400, _('You are already logged in'));

        if(!osc_users_enabled()) $this->abort(400, _m('Users are not enabled'));

        $email = trim(Params::getParam('email'));
        $password = Params::getParam('password');

        if($email == '') $this->abort(400, _m('Please provide an email address'));
        if($password == '') $this->abort(400, _m('Empty passwords are not allowed. Please provide a password'));

        if(osc_validate_email($email)) $user = $this->userDAO->findByEmail($email);
        if(empty($user)) $user = $this->userDAO->findByUsername($email);
        
        if(empty($user)) $this->abort(404, _m('The user doesn\'t exist'));

        if(!osc_verify_password($password, (isset($user['s_password']) ? $user['s_password'] : ''))) $this->abort(400, _m('The password is incorrect'));
        
        if($user['s_password'] != '') {
            if(preg_match('|\$2y\$([0-9]{2})\$|', $user['s_password'], $cost)) {
                if($cost[1] != BCRYPT_COST)
                    $this->userDAO->updateByPrimaryKey(['s_password' => osc_hash_password($password)], $user['pk_i_id']);
            } else {
                $this->userDAO->updateByPrimaryKey(['s_password' => osc_hash_password($password)], $user['pk_i_id']);
            }
        }

        $banned = osc_is_banned($email);
        if($banned & 1) $this->abort(403, _m('Your current email is not allowed'));
        if($banned & 2) $this->abort(403, _m('Your current IP is not allowed'));

        if(!$user['b_active']) $this->abort(400, _m('The user has not been validated yet'));
        if(!$user['b_enabled']) $this->abort(400, _m('The user has been suspended'));

        $time = time();
        $refreshToken = $this->refreshToken($time, $user);
        $accessToken = $this->accessToken($time, $user);
        
        return $this->json(['status' => 1, 'refresh_token' => $refreshToken, 'access_token' => $accessToken]);
    }

    private function refreshToken($time, $user) {
        $refreshDuration = (Params::getParam('rememberMe')) ? self::REFRESH_TOKEN_DURATION_REMEMBER_ME : self::REFRESH_TOKEN_DURATION;
        $refreshSecret = osc_genRandomPassword(32);
        
        $refreshToken = JWT::generate([
            'iss' => osc_base_url(),
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + $refreshDuration,
            'sub' => $user['pk_i_id'],
            'jti' => $refreshSecret,
        ]);

        $this->refreshTokenDAO->insert([
            'fk_i_user_id' => $user['pk_i_id'],
            's_token' => osc_hash_password($refreshToken),
            's_secret' => $refreshSecret,
            'dt_expires' => date('Y-m-d H:i:s', $time + $refreshDuration),
        ]);

        return $refreshToken;
    }

    private function accessToken($time, $user) {
        $accessToken = JWT::generate([
            'iss' => osc_base_url(),
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + self::ACCESS_TOKEN_DURATION,
            'sub' => $user['pk_i_id'],
            'name' => $user['s_name'],
        ]);

        return $accessToken;
    }

    public function logout() {
        $refreshToken = Params::getParam('refresh_token');
        if(empty($refreshToken)) $this->abort(400, 'No refresh token provided.');

        $refreshParsed = DFTDAPI_JWT::parse($refreshToken);
        if($refreshParsed === 0) $this->abort(400, 'Invalid refresh token provided.');

        $this->refreshTokenDAO->deleteByUserSecret($refreshParsed->sub, $refreshParsed->jti);
    
        return $this->json(['status' => 1]);
    }

    public function refresh() {
        $refreshToken = Params::getParam('refresh_token');
        if(empty($refreshToken)) $this->abort(403, 'No refresh token provided.');

        $refreshParsed = DFTDAPI_JWT::parse($refreshToken, false);
        
        if($refreshParsed === 0) $this->abort(403, 'Invalid refresh token provided.');
        if(($refreshParsed->exp - time()) < 0) {
            $this->refreshTokenDAO->deleteByUserSecret($refreshParsed->sub, $refreshParsed->jti);
            $this->abort(403, 'Expired refresh token provided.');
        }

        // Refresh token is valid only if it's found in the database with same user and secret.
        $refreshRow = $this->refreshTokenDAO->findByUserSecret($refreshParsed->sub, $refreshParsed->jti);

        if(!$refreshRow) $this->abort(403, 'Invalid refresh token provided.');

        if(!osc_verify_password($refreshToken, $refreshRow['s_token'])) $this->abort(403, 'Invalid refresh token provided.');

        $user = $this->userDAO->findByPrimaryKey($refreshParsed->sub);

        // Generate the access token.
        $time = time();
        $accessToken = DFTDAPI_JWT::generate([
            'iss' => osc_base_url(),
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + self::ACCESS_TOKEN_DURATION,
            'sub' => $user['pk_i_id'],
            'name' => $user['s_name'],
        ]);
        
        return $this->json(['status' => 1, 'access_token' => $accessToken]);
    }

    public function view(int $id) {
        $user = $this->userDAO->findByPrimaryKey($id);
        
        if(!$user) $this->abort(404);

        $response = [
            's_name' => $user['s_name'],
            's_country' => $user['s_country'],
            's_region' => $user['s_region'],
            's_city' => $user['s_city'],
            'dt_reg_date' => $user['dt_reg_date'],
        ];

        if(function_exists('profilepic_user_url')) {
            $response['avatar'] = profilepic_user_url($id);
        }

        return $this->json($response);
    }

    public function items() {
        $user = (int) Params::getParam('user');
        $itemType = false;

        $this->_items($user, $itemType);
    }

    public function myItems() {
        $user = osc_logged_user_id();
        $itemType = (Params::getParam('type') != '') ? Params::getParam('type') : false;

        $this->_items($user, $itemType);
    }

    public function _items($user, $itemType) {
        $perPage = 7; // Get from request.
        $page = ((int) Params::getParam('iPage')) ? (int) Params::getParam('iPage') - 1 : 0;
        $totalItems = Item::newInstance()->countItemTypesByUserID($user, $itemType);

        $items = Item::newInstance()->findItemTypesByUserID($user, $page * $perPage, $perPage, $itemType);

        foreach($items as $key => $item) {
            unset($items[$key]['s_secret']);
            $items[$key]['resources'] = ItemResource::newInstance()->getAllResourcesFromItem($item['pk_i_id']);
        }

        $items = osc_apply_filter('api_pre_show_items', $items, 'user');

        return $this->json(['items' => $items, 'total' => $totalItems]);
    }

    public function itemCount($type) {
        echo Item::newInstance()->countItemTypesByUserID(osc_logged_user_id(), $type);
    }

    public function add() {
        if(osc_is_web_user_logged_in()) $this->abort(400, _('You are already logged in'));

        if(!osc_users_enabled()) $this->abort(400, _m('Users are not enabled'));

        osc_run_hook('api_before_user_register');

        $banned = osc_is_banned(Params::getParam('s_email'));
        if($banned == 1) $this->abort(403, _m('Your current email is not allowed'));
        else if($banned == 2) $this->abort(403, _m('Your current IP is not allowed'));

        $userActions = new UserActions(false);
        $success = $userActions->add();

        if(!is_int($success)) $this->abort(400, $success);

        osc_run_hook('api_after_user_register');

        if($success == 1) {
            return $this->json(['status' => 1, 'message' => _m('The user has been created. An activation email has been sent')]);
        } else if($success == 2) {
            return $this->json(['status' => 2, 'message' => _m('Your account has been created successfully')]);
        }
    }

    public function edit() {
        $userActions = new UserActions(false);
        $success = $userActions->edit(osc_logged_user_id());

        if($success) {
            return $this->json(['status' => 1, 'message' => _m('Your profile has been updated successfully')]);
        } else {
            $this->abort(400, $success);
        }
    }

    public function editEmail() {
        $email = Params::getParam('email');

        if(!osc_validate_email($email)) $this->abort(400, _m('The specified e-mail is not valid'));

        $user = $this->userDAO->findByEmail($email);
        if(isset($user['pk_i_id'])) $this->abort(400, _m('The specified e-mail is already in use'));

        $userEmailTmp = array();
        $userEmailTmp['fk_i_user_id'] = osc_logged_user_id();
        $userEmailTmp['s_new_email'] = $email;

        UserEmailTmp::newInstance()->insertOrUpdate($userEmailTmp);

        $code = osc_genRandomPassword(30);
        $date = date('Y-m-d H:i:s');

        $this->userDAO->updateByPrimaryKey([
            's_pass_code' => $code,
            's_pass_date' => $date,
            's_pass_ip' => Params::getServerParam('REMOTE_ADDR')
        ], osc_logged_user_id());

        return $this->json(['status' => 1]);
    }

    public function editUsername() {
        $username = Params::getParam('username');

        if($username == '') $this->abort(400, _m('The specified username could not be empty'));

        $user = $this->userDAO->findByUsername($username);

        if(isset($user['s_username'])) $this->abort(400, _m('The specified username is already in use'));
        if(osc_is_username_blacklisted($username)) $this->abort(400, _m('The specified username is not valid, it contains some invalid words'));
 
        $this->userDAO->updateByPrimaryKey(['s_username' => $username], osc_logged_user_id());

        return $this->json(['status' => 1]);        
    }

    public function editPassword() {
        $password = Params::getParam('password');
        $newPassword = Params::getParam('newPassword');
        $newPassword2 = Params::getParam('newPassword2');

        if($password == '' || $newPassword == '' || $newPassword2 == '') $this->abort(400, _m('Password cannot be blank'));
        if($newPassword != $newPassword2) $this->abort(400, _m('Passwords don\'t match'));

        $user = $this->userDAO->findByPrimaryKey(osc_logged_user_id());
        if(!osc_verify_password($password, $user['s_password'])) $this->abort(400, _m('Current password doesn\'t match'));

        $this->userDAO->updateByPrimaryKey(['s_password' => osc_hash_password($newPassword)], osc_logged_user_id());

        // Logout all other devices logged in the same account.
        $refreshToken = Params::getParam('refresh_token');
        if(!empty($refreshToken)) {
            $refreshParsed = DFTDAPI_JWT::parse($refreshToken);
            if($refreshParsed) {
                $this->refreshTokenDAO->deleteExceptSecret(osc_logged_user_id(), $refreshParsed->jti);
            }
        }

        return $this->json(['status' => 1]);
    }
    
    public function delete() {
        $user = $this->userDAO->findByPrimaryKey(osc_logged_user_id());

        osc_run_hook('before_user_delete', $user);

        return $this->json(['status' => $this->userDAO->deleteUser(osc_logged_user_id())]);
    }

    public function resetPassword() {
        if(!Params::getParam('password')) $this->abort(400, _m('Password cannot be blank'));

        // Get user by email and code.
        $this->userDAO->dao->select();
        $this->userDAO->dao->from($this->userDAO->getTableName());
        $this->userDAO->dao->where('s_email', Params::getParam('email'));
        $this->userDAO->dao->where('s_pass_code', Params::getParam('code'));
        $this->userDAO->dao->where(sprintf('s_pass_date >= "%s"', date('Y-m-d H:i:s', (time() - (24 * 3600)))));
        $result = $this->userDAO->dao->get();
    
        if(!$result) $this->abort(400, _m('Invalid credentials'));
        $user = $result->row();
        if(!count($user)) $this->abort(400, _m('Invalid credentials'));

        if(!$user['b_enabled']) $this->abort(400, _m('Sorry, the link is not valid'));

        $this->userDAO->updateByPrimaryKey([
            's_pass_code' => osc_genRandomPassword(50),
            's_pass_date' => date('Y-m-d H:i:s', 0),
            's_pass_ip' => Params::getServerParam('REMOTE_ADDR'),
            's_password' => osc_hash_password(Params::getParam('password'))
        ], $user['pk_i_id']);
        
        // Logout all other devices logged in the same account.
        $this->refreshTokenDAO->delete(['fk_i_user_id' => $user['pk_i_id']]);

        return $this->json(['status' => 1, 'message' => _m('The password has been changed')]);
    }

    public function validate() {
        // Get user by email and secret.
        $this->userDAO->dao->select();
        $this->userDAO->dao->from($this->userDAO->getTableName());
        $this->userDAO->dao->where('s_email', Params::getParam('email'));
        $this->userDAO->dao->where('s_secret', Params::getParam('code'));
        $this->userDAO->dao->where('b_active', '0');
        $result = $this->userDAO->dao->get();
    
        if(!$result) $this->abort(400, _m('Invalid credentials'));
        $user = $result->row();
        if(!count($user)) $this->abort(400, _m('Invalid credentials'));

        $this->userDAO->updateByPrimaryKey(['b_active' => 1], $user['pk_i_id']);

        osc_run_hook('hook_email_user_registration', $user);
        osc_run_hook('api_validate_user', $user);

        $time = time();
        $refreshToken = $this->refreshToken($time, $user);
        $accessToken = $this->accessToken($time, $user);

        return $this->json(['status' => 1, 'refresh_token' => $refreshToken, 'access_token' => $accessToken]);
    }

    public function forgotPassword() {
        if(!osc_validate_email(Params::getParam('s_email'))) $this->abort(400, _m('Invalid email address'));

        $userActions = new UserActions(false);
        $success = $userActions->recover_password();

        return $this->json(['status' => $success]);
    }
}