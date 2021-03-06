<?php if ( ! defined('ABS_PATH')) exit('ABS_PATH is not loaded. Direct access is not allowed.');

    /**
     * Osclass – software for creating and publishing online classified advertising platforms
     *
     * Copyright (C) 2012 OSCLASS
     *
     * This program is free software: you can redistribute it and/or modify it under the terms
     * of the GNU Affero General Public License as published by the Free Software Foundation,
     * either version 3 of the License, or (at your option) any later version.
     *
     * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
     * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
     * See the GNU Affero General Public License for more details.
     *
     * You should have received a copy of the GNU Affero General Public
     * License along with this program. If not, see <http://www.gnu.org/licenses/>.
     */

    class CWebUserNonSecure extends BaseModel
    {
        function __construct()
        {
            parent::__construct();
            if( !osc_users_enabled() && ($this->action != 'activate_alert' && $this->action != 'unsub_alert') ) {
                osc_add_flash_error_message( _m('Users not enabled') );
                $this->redirectTo(osc_base_url());
            }
        }

        //Business Layer...
        function doModel()
        {
            switch( $this->action ) {
                case 'change_email_confirm':    //change email confirm
                                                if ( Params::getParam('userId') && Params::getParam('code') ) {
                                                    $userManager = new User();
                                                    $user = $userManager->findByPrimaryKey( Params::getParam('userId') );

                                                    if( $user['s_pass_code'] == Params::getParam('code') && $user['b_enabled']==1) {
                                                        $userEmailTmp = UserEmailTmp::newInstance()->findByPrimaryKey( Params::getParam('userId') );
                                                        $code = osc_genRandomPassword(50);
                                                        $userManager->update(
                                                             array('s_email' => $userEmailTmp['s_new_email'])
                                                            ,array('pk_i_id' => $userEmailTmp['fk_i_user_id'])
                                                        );
                                                        Item::newInstance()->update(array('s_contact_email' => $userEmailTmp['s_new_email']), array('fk_i_user_id' => $userEmailTmp['fk_i_user_id']));
                                                        ItemComment::newInstance()->update(array('s_author_email' => $userEmailTmp['s_new_email']), array('fk_i_user_id' => $userEmailTmp['fk_i_user_id']));
                                                        Alerts::newInstance()->update(array('s_email' => $userEmailTmp['s_new_email']), array('fk_i_user_id' => $userEmailTmp['fk_i_user_id']));
                                                        Session::newInstance()->_set('userEmail', $userEmailTmp['s_new_email']);
                                                        UserEmailTmp::newInstance()->delete(array('s_new_email' => $userEmailTmp['s_new_email']));
                                                        osc_add_flash_ok_message( _m('Your email has been changed successfully'));
                                                        $this->redirectTo( osc_user_profile_url() );
                                                    } else {
                                                        osc_add_flash_error_message( _m('Sorry, the link is not valid'));
                                                        $this->redirectTo( osc_base_url() );
                                                    }
                                                } else {
                                                    osc_add_flash_error_message( _m('Sorry, the link is not valid'));
                                                    $this->redirectTo( osc_base_url() );
                                                }
                break;
                case 'activate_alert':
                    $email  = Params::getParam('email');
                    $secret = Params::getParam('secret');
                    $id     = Params::getParam('id');

                    $alert = Alerts::newInstance()->findByPrimaryKey($id);
                    $result = 0;
                    if(!empty($alert)) {
                        if($email==$alert['s_email'] && $secret==$alert['s_secret']) {
                            $user = User::newInstance()->findByEmail($alert['s_email']);
                            if(isset($user['pk_i_id'])) {
                                Alerts::newInstance()->update(array('fk_i_user_id' => $user['pk_i_id']), array('pk_i_id' => $id));
                            }
                            $result = Alerts::newInstance()->activate($id);
                        }
                    }

                    if( $result == 1 ) {
                        osc_add_flash_ok_message(_m('Alert activated'));
                    }else{
                        osc_add_flash_error_message(_m('Oops! There was a problem trying to activate your alert. Please contact an administrator'));
                    }

                    $this->redirectTo( osc_base_url() );
                break;
                case 'unsub_alert':
                    $email  = Params::getParam('email');
                    $secret = Params::getParam('secret');
                    $id     = Params::getParam('id');

                    $alert  = Alerts::newInstance()->findByPrimaryKey($id);
                    $result = 0;
                    if(!empty($alert)) {
                        if($email==$alert['s_email'] && $secret==$alert['s_secret']) {
                            $result = Alerts::newInstance()->unsub($id);
                        }
                    }

                    if( $result == 1 ) {
                        osc_add_flash_ok_message(_m('Unsubscribed correctly'));
                    }else{
                        osc_add_flash_error_message(_m('Oops! There was a problem trying to unsubscribe you. Please contact an administrator'));
                    }

                    $this->redirectTo(osc_base_url());
                break;
                case 'pub_profile':
                    if(Params::getParam('username')!='') {
                        $user = User::newInstance()->findByUsername(Params::getParam('username'));
                    } else {
                        $user = User::newInstance()->findByPrimaryKey(Params::getParam('id'));
                    }
                    // user doesn't exist, show 404 error
                    if( !$user ) {
                        $this->do404();
                        return;
                    }

                    View::newInstance()->_exportVariableToView( 'user', $user );
                    $mSearch = Search::newInstance();
                    $mSearch->fromUser($user['pk_i_id']);

                    $items = $mSearch->doSearch();
                    $count = $mSearch->count();

                    View::newInstance()->_exportVariableToView( 'items', $items );
                    View::newInstance()->_exportVariableToView( 'search_total_items', $count );

                    $this->doView('user-public-profile.php');
                break;
                case 'contact_post':
                    $user = User::newInstance()->findByPrimaryKey( Params::getParam('id') );
                    View::newInstance()->_exportVariableToView('user', $user);
                    if ((osc_recaptcha_private_key() != '')) {
                        if(!osc_check_recaptcha()) {
                            osc_add_flash_error_message( _m('The Recaptcha code is wrong'));
                            Session::newInstance()->_setForm("yourEmail",   Params::getParam('yourEmail'));
                            Session::newInstance()->_setForm("yourName",    Params::getParam('yourName'));
                            Session::newInstance()->_setForm("phoneNumber", Params::getParam('phoneNumber'));
                            Session::newInstance()->_setForm("message_body",Params::getParam('message'));
                            $this->redirectTo( osc_user_public_profile_url( ) );
                            return false; // BREAK THE PROCESS, THE RECAPTCHA IS WRONG
                        }
                    }

                    osc_run_hook('hook_email_contact_user', Params::getParam('id'), Params::getParam('yourEmail'), Params::getParam('yourName'), Params::getParam('phoneNumber'), Params::getParam('message'));

                    $this->redirectTo( osc_user_public_profile_url( ) );
                break;
                default:
                    $this->redirectTo( osc_user_login_url() );
                break;
            }
        }

        //hopefully generic...
        function doView($file)
        {
            osc_run_hook("before_html");
            osc_current_web_theme_path($file);
            Session::newInstance()->_clearVariables();
            osc_run_hook("after_html");
        }
    }

    /* file end: ./user-non-secure.php */
?>