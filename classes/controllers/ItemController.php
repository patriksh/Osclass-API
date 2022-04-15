<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Controllers;

use \DFTDAPI\Actions\ItemActions as ItemActions;
use Item;
use ItemResource;
use ItemStats;
use ItemComment;
use User;
use Log;
use Field;
use Params;
use AjaxUploader;
use ImageProcessing;

class ItemController extends Controller {
    public $itemDAO;
    public $userDAO;

    public function __construct() {
        parent::__construct();
        $this->itemDAO = Item::newInstance();
        $this->userDAO = User::newInstance();
    }

    public function view(int $id) {
        $item = osc_apply_filter('api_pre_show_item', $this->itemDAO->findByPrimaryKey($id));

        if(!count($item)) $this->abort(404);

        if($item['b_active'] != 1) {
            if(osc_is_web_user_logged_in() && osc_logged_user_id() == $item['fk_i_user_id']) {
                $item['message'] = _m('The listing hasn\'t been validated. Please validate it in order to make it public');
            } else {
                $this->abort(404);
            }
        } else if($item['b_enabled'] == 0) {
            if(osc_is_web_user_logged_in() && osc_logged_user_id() == $item['fk_i_user_id']) {
               $item['message'] = _m('The listing has been blocked or is awaiting moderation from the admin');
            } else {
                $this->abort(404);
            }
        }

        if(!osc_is_web_user_logged_in() || $item['fk_i_user_id'] == null || osc_logged_user_id() != $item['fk_i_user_id']) {
            $stats = new ItemStats();
            $stats->increase('i_num_views', $item['pk_i_id']);
        }

        $item['meta'] = $this->itemDAO->metaFields($item['pk_i_id']);
        $item['resources'] = ItemResource::newInstance()->getAllResourcesFromItem($item['pk_i_id']);
        $item['s_description'] = strip_tags($item['s_description']);

        $item['s_contact_email'] = ($item['b_show_email']) ? $item['s_contact_email'] : '';
        if(function_exists('profilepic_user_url') && $item['fk_i_user_id'] != null) {
            $item['s_contact_avatar'] = profilepic_user_url($item['fk_i_user_id']);
        }

        return $this->json($item);
    }

    public function add() {
        $mItems = new ItemActions(false);
        $mItems->prepareData(true);

        if(osc_reg_user_post() && !osc_is_web_user_logged_in()) $this->abort(403, _m('Only registered users are allowed to post listings'));

        if(!osc_is_web_user_logged_in()) {
            $user = $this->userDAO->findByEmail($mItems->data['contactEmail']);
            if(isset($user['pk_i_id'])) $this->abort(400, _m('A user with that email address already exists, if it is you, please log in'));
        }

        $banned = osc_is_banned($mItems->data['contactEmail']);
        if($banned == 1) $this->abort(403, _m('Your current email is not allowed'));
        else if($banned == 2) $this->abort(403, _m('Your current IP is not allowed'));

        $success = $mItems->add();

        if(!is_int($success)) $this->abort(400, $success);

        if($success == 1) {
            return $this->json(['status' => 1, 'message' => _m('Check your inbox to validate your listing')]);
        } else if(function_exists('osc_moderate_admin_post') && osc_moderate_admin_post()) {
            return $this->json(['status' => 1, 'message' => _m('Your listing will be published after an admin approves it.')]);
        } else {
            return $this->json(['status' => 2, 'message' => _m('Your listing has been published'), 'id' => Params::getParam('itemId')]);
        }
    }

    public function edit() {
        $mItems = new ItemActions(false);
        $mItems->prepareData(false);

        $secret = Params::getParam('secret');
        $id = (int) Params::getParam('id');
        $item = $this->itemDAO->listWhere(
            'i.pk_i_id = %d AND ((i.s_secret = %s AND i.fk_i_user_id IS NULL) OR (i.fk_i_user_id = %d))',
            (int) $id,
            $secret,
            (int) osc_logged_user_id()
        );

        if(count($item) != 1) $this->abort(400, _m('Invalid item'));

        $success = $mItems->edit();

        if(!is_int($success)) $this->abort(400, $success);

        if(function_exists('osc_moderate_admin_edit') && osc_moderate_admin_edit()) {
            return $this->json(['status' => 1, 'message' => _m('Your listing will be published after an admin approves the changes.')]);
        } else {
            return $this->json(['status' => 1, 'message' => _m('Your listing will be published after an admin approves the changes.')]);
        }
    }

    public function activate() {
        $id = (int) Params::getParam('id');
        $secret = Params::getParam('secret');
        $item = $this->itemDAO->listWhere(
            'i.pk_i_id = %d AND ((i.s_secret = %s) OR (i.fk_i_user_id = %d))',
            (int) $id,
            $secret,
            (int) osc_logged_user_id()
        );

        if(count($item) != 1) $this->abort(400, _m('Invalid item'));

        $item = $item[0];
        if($item['b_active']) $this->abort(400, _m('The listing has already been validated'));

        $mItems = new ItemActions(false);
        $success = $mItems->activate($item['pk_i_id'], $item['s_secret']);

        if($success) {
            return $this->json(['status' => 1, 'message' => _m('The listing has been validated')]);
        } else {
            return $this->json(['status' => 0, 'message' => _m('The listing can\'t be validated')]);
        }
    }

    public function delete(int $id) {
        $item = $this->itemDAO->listWhere(
            'i.pk_i_id = %d AND i.fk_i_user_id = %d',
            $id,
            osc_logged_user_id()
        );

        if(count($item) != 1) $this->abort(400, _m('Invalid item'));

        $item = $item[0];
        $mItems = new ItemActions(false);
        $success = $mItems->delete($item['s_secret'], $item['pk_i_id']);

        if($success) {
            return $this->json(['status' => 1, 'message' => _m('Your listing has been deleted')]);
        } else {
            $this->abort(400, _m('The listing you are trying to delete couldn\'t be deleted'));
        }
    }

    public function addComment() {
        $id = (int) Params::getParam('id');
        $item = $this->itemDAO->findByPrimaryKey($id);

        osc_run_hook('api_pre_item_add_comment_post', $item);

        $mItems = new ItemActions(false);
        switch($mItems->add_comment()) {
            case -1:
                $response = ['status' => 0, 'message' => _m('Sorry, we could not save your comment. Try again later')];
            break;
            case 1:
                $response = ['status' => 1, 'message' => _m('Your comment is awaiting moderation')];
            break;
            case 2:
                $response = ['status' => 1, 'message' => _m('Your comment has been approved')];
            break;
            case 3:
                $this->abort(400, _m('Please fill the required field (email)'));
            break;
            case 4:
                $this->abort(400, _m('Please type a comment'));
            break;
            case 5:
                $response = ['status' => 1, 'message' => _m('Your comment has been marked as spam')];
            break;
            case 6:
                $this->abort(400, _m('You need to be logged to comment'));
            break;
            case 7:
                $this->abort(400, _m('Sorry, comments are disabled'));
            break;
        }
        
        return $this->json($response);
    }

    public function deleteComment() {
        $commentId = (int) Params::getParam('comment');
        $id = (int) Params::getParam('id');
        $item = $this->itemDAO->findByPrimaryKey($id);

        osc_run_hook('api_pre_item_delete_comment_post', $item, $commentId);

        if(!count($item)) $this->abort(400, _m('Invalid item'));

        $mItems = new ItemActions(false);
        $mItems->add_comment();

        $commentDAO = ItemComment::newInstance();
        $comment = $commentDAO->findByPrimaryKey($commentId);

        if(!count($comment)) $this->abort(400, _m('Invalid comment'));
        if($comment['b_active'] != 1) $this->abort(400, _m('The comment is not active, you cannot delete it'));
        if($comment['fk_i_user_id'] != osc_logged_user_id()) $this->abort(403, _m('The comment was not added by you, you cannot delete it'));

        $success = $commentDAO->deleteByPrimaryKey($commentId);

        if($success) {
            return $this->json(['status' => 1, 'message' => _m('The comment has been deleted')]);
        } else {
            return $this->json(['status' => 0, 'message' => _m('The comment you are trying to delete couldn\'t be deleted')]);
        }
    }

    public function contact() {
        if(osc_reg_user_can_contact() && !osc_is_web_user_logged_in()) $this->abort(403, _m("You can't contact the seller, only registered users can"));

        $id = (int) Params::getParam('id');
        $item = $this->itemDAO->findByPrimaryKey($id);

        $banned = osc_is_banned(Params::getParam('yourEmail'));
        if($banned == 1) $this->abort(403, _m('Your current email is not allowed'));
        else if($banned == 2) $this->abort(403, _m('Your current IP is not allowed'));

        if(osc_isExpired($item['dt_expiration'])) $this->abort(400, _m("We're sorry, but the listing has expired. You can't contact the seller"));

        osc_run_hook('api_pre_item_contact_post', $item);

        $mItems = new ItemActions(false);
        $success = $mItems->add_comment();

        osc_run_hook('api_post_item_contact_post', $item);

        if(!is_string($success)) {
            return $this->json(['status' => 1, 'message' => _m('We\'ve just sent an e-mail to the seller')]);
        } else {
            osc_add_flash_ok_message();
            return $this->json(['status' => 0, 'message' => $success]);
        }
    }

    public function mark() {
        $as = Params::getParam('as');
        $id = (int) Params::getParam('id');

        $mItems = new ItemActions(false);
        $success = $mItems->mark($id, $as);

        return $this->json(['status' => 1, 'message' => _m('Thanks! That\'s very helpful')]);
    }

    public function imageUpload() {
        $uploader = new AjaxUploader();
        $original = pathinfo($uploader->getOriginalName());
        $ip = Params::getServerParam('REMOTE_ADDR');
        $uniqid = uniqid('_apifile_', true);
        $filename = $ip . $uniqid . '.' . $original['extension'];

        try {
            $uploader->handleUpload(osc_content_path() . 'uploads/temp/' . $filename);
        } catch (Exception $e) {
            $this->abort(400, $e->getMessage());
        }

        $img = ImageProcessing::fromFile(osc_content_path() . 'uploads/temp/' . $filename);

        try {
            $img->saveToFile(osc_content_path() . 'uploads/temp/auto_' . $filename, $original['extension']);
        } catch (Exception $e) {
            $this->abort(400, $e->getMessage());
        }

        try {
            $img->saveToFile(osc_content_path() . 'uploads/temp/' . $filename, $original['extension']);
        } catch (Exception $e) {
            $this->abort(400, $e->getMessage());
        }

        return $this->json([
            'status' => 1,
            'name' => 'auto_' . $filename,
        ]);
    }

    public function imageDelete($name) {
        $name = str_replace('-', '.', osc_sanitize_string($name)); // Escape any weird stuff but preserve dots as they're part of the proper file name.
        $otherName = str_replace('auto_', '', $name); // Why aren't "auto_" images deleted by default in Osclass??

        // Confirm file that's requested to be deleted starts with same IP.
        $search = 'auto_' . Params::getServerParam('REMOTE_ADDR') . '_apifile_';
        if(substr($name, 0, strlen($search)) === $search) {
            @unlink(osc_content_path() . 'uploads/temp/' . $name);
            @unlink(osc_content_path() . 'uploads/temp/' . $otherName);

            return $this->json(1);
        } else {
            $this->abort(400);
        }
    }

    public function imageDeleteExisting() {
        $id = (int) Params::getParam('id');
        $itemId = (int) Params::getParam('item');
        $code = Params::getParam('code');

        if(!$id || !$itemId || !preg_match('/^([a-z0-9]+)$/i', $code))
            $this->abort(400, _m('The selected photo couldn\'t be deleted, the url doesn\'t exist'));

        $item = $this->itemDAO->findByPrimaryKey($itemId);
        if(!count($item))
            $this->abort(404, _m('The listing doesn\'t exist'));
        
        if(osc_logged_user_id() != $item['fk_i_user_id'])
            $this->abort(403, _m('The listing doesn\'t belong to you'));

        $result = ItemResource::newInstance()->existResource($id, $code);
        if(!$result)
            $this->abort(400, _m('The selected photo couldn\'t be deleted'));

        $resource = ItemResource::newInstance()->findByPrimaryKey($id);
        if($resource['fk_i_item_id'] != $itemId)
            $this->abort(403, _m('The selected photo does not belong to you'));

        osc_deleteResource($id, false);
        Log::newInstance()->insertLog('item', 'deleteResource', $id, $id, 'user', osc_logged_user_id());
        ItemResource::newInstance()->delete(['pk_i_id' => $id, 'fk_i_item_id' => $itemId, 's_name' => $code]);

        return 1;
    }
}