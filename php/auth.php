<?php
    /*
     * $Id: auth.php 1226 2008-04-26 15:34:16Z dmorton $
     *
     * MAIA MAILGUARD LICENSE v.1.0
     *
     * Copyright 2004 by Robert LeBlanc <rjl@renaissoft.com>
     *                   David Morton   <mortonda@dgrmm.net>
     * All rights reserved.
     *
     * PREAMBLE
     *
     * This License is designed for users of Maia Mailguard
     * ("the Software") who wish to support the Maia Mailguard project by
     * leaving "Maia Mailguard" branding information in the HTML output
     * of the pages generated by the Software, and providing links back
     * to the Maia Mailguard home page.  Users who wish to remove this
     * branding information should contact the copyright owner to obtain
     * a Rebranding License.
     *
     * DEFINITION OF TERMS
     *
     * The "Software" refers to Maia Mailguard, including all of the
     * associated PHP, Perl, and SQL scripts, documentation files, graphic
     * icons and logo images.
     *
     * GRANT OF LICENSE
     *
     * Redistribution and use in source and binary forms, with or without
     * modification, are permitted provided that the following conditions
     * are met:
     *
     * 1. Redistributions of source code must retain the above copyright
     *    notice, this list of conditions and the following disclaimer.
     *
     * 2. Redistributions in binary form must reproduce the above copyright
     *    notice, this list of conditions and the following disclaimer in the
     *    documentation and/or other materials provided with the distribution.
     *
     * 3. The end-user documentation included with the redistribution, if
     *    any, must include the following acknowledgment:
     *
     *    "This product includes software developed by Robert LeBlanc
     *    <rjl@renaissoft.com>."
     *
     *    Alternately, this acknowledgment may appear in the software itself,
     *    if and wherever such third-party acknowledgments normally appear.
     *
     * 4. At least one of the following branding conventions must be used:
     *
     *    a. The Maia Mailguard logo appears in the page-top banner of
     *       all HTML output pages in an unmodified form, and links
     *       directly to the Maia Mailguard home page; or
     *
     *    b. The "Powered by Maia Mailguard" graphic appears in the HTML
     *       output of all gateway pages that lead to this software,
     *       linking directly to the Maia Mailguard home page; or
     *
     *    c. A separate Rebranding License is obtained from the copyright
     *       owner, exempting the Licensee from 4(a) and 4(b), subject to
     *       the additional conditions laid out in that license document.
     *
     * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER AND CONTRIBUTORS
     * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
     * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
     * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
     * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
     * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
     * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
     * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
     * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
     * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
     * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
     *
     */

    require_once ("core.php");
    require_once ("MDB2.php");  // PEAR::DB
    require_once ("mailtools.php");
    require_once ("maia_db.php");


    /* Authenticate with external program declared as  $auth_external
     * in config.php
     */
    function auth_external($user, $pass)
    {
	   if ($user == "") {  // Don't bother authenticating an empty username
		return false;      // ticket #335
	   }
       global $auth_external;
       $safe_user=escapeshellcmd($user);
       $safe_pass=escapeshellcmd($pass);
       system("$auth_external \"$safe_user\" \"$safe_pass\"",$retval);
       if ($retval == 0) {
          return true;
       } else {
          return false;
       }
    }
    /*
     * auth_pop3(): Authenticate against a POP3 server.
     */
    function auth_pop3($user, $pass)
    {
	   if ($user == "") {  // Don't bother authenticating an empty username
		return false;      // ticket #335
	   }
        global $auth_pop3_host;
        global $auth_pop3_port;
        
        if (!isset($auth_pop3_host)) {
          $auth_pop3_host = "localhost";
        }
        
        if (!isset($auth_pop3_port)) {
          $auth_pop3_port = 110;
        }
        

        require_once ("Net/POP3.php");
        $mbox = new Net_POP3();
        
        $result = $mbox->connect($auth_pop3_host, $auth_pop3_port);
         if (PEAR::isError($mbox)) {
              return $result;
        }

        $result = $mbox->login($user, $pass);
        if (PEAR::isError($mbox)) {
              $mbox->disconnect();
              return $result;
        }
        
        if ($result === true) {
            $mbox->disconnect();
            return true;
        } else {
            $mbox->disconnect();
            return $result;
        }
    }


    /*
     * auth_imap(): Authenticate against an IMAP server.
     */
    function auth_imap($user, $pass)
    {
	   if ($user == "") {  // Don't bother authenticating an empty username
		return false;      // ticket #335
	   }
        global $auth_imap_host;
        global $auth_imap_port;
        
        if (!isset($auth_imap_host)) {
          $auth_imap_host = "localhost";
        }
        
        if (!isset($auth_imap_port)) {
          $auth_imap_port = 143;
        }
        
        require_once ("Net/IMAP.php");
        $mbox = new Net_IMAP( $auth_imap_host, $auth_imap_port);

        if (PEAR::isError($mbox)) {
              //echo $mbox->toString();
              return $mbox;
        }
        

        $result = $mbox->login($user, $pass, false, false);     
        if (PEAR::isError($result)) {
              //echo $result->toString();
              $mbox->disconnect();
              return $result;
        }       
        if ($result === true) {
            $mbox->disconnect();
            return true;
        } else {
            $mbox->disconnect();
            return $result;
        }
    }


    /*
     * auth_ldap(): Authenticate against an LDAP server.
     *              Code contributed by David Morton <mortonda@osprey.net>.
     */
    function auth_ldap($user, $pass)
    {
	   if ($user == "") {  // Don't bother authenticating an empty username
		return false;      // ticket #335
	   }
    	global $dbh;
        global $lang;
        global $auth_ldap_server;
        global $auth_ldap_bind_dn;
        global $auth_ldap_base_dn;
        global $auth_ldap_password;
        global $auth_ldap_query;
        global $auth_ldap_attribute;
        global $auth_ldap_version;
        global $auth_ldap_opt_referrals;
        global $auth_ldap_use_tls;
        

        $ldap_conn = ldap_connect($auth_ldap_server)
                         or die($lang['error_ldap_connect']);
        
        if(isset($auth_ldap_version)) {                 
          ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, $auth_ldap_version);
        }
        
        if(isset($auth_ldap_opt_referrals)) {
          ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, $auth_ldap_opt_referrals);
        }
        
        # if using tls: attempt to start tls
        if (isset($auth_ldap_use_tls) && $auth_ldap_use_tls == "true") {
            @ldap_start_tls($ldap_conn)
                or die($lang['error_ldap_start_tls']);
        }
        
        @ldap_bind($ldap_conn, $auth_ldap_bind_dn, $auth_ldap_password)
            or die($lang['error_ldap_bind']);

        $filter = $auth_ldap_query;
        $filter = str_replace("%%USER%%", $user, $filter);

        $sr = ldap_search($ldap_conn, $auth_ldap_base_dn, $filter,
                          array($auth_ldap_attribute, "dn"));

        if (ldap_count_entries($ldap_conn, $sr) == 1) { // found user
            $entries = ldap_get_entries($ldap_conn, $sr);
            $userdn = $entries["0"]["dn"];
            $routingaddress = $entries["0"]["$auth_ldap_attribute"]["0"];

            if (@ldap_bind($ldap_conn, $userdn, $pass)) {
                ldap_close($ldap_conn);
                return $routingaddress;
            } else {
            	ldap_close($ldap_conn);
            	return false;
            }
        } else { // not found: too many entries?
            ldap_close($ldap_conn);
            return false;
        }
    }


    /*
     * auth_exchange(): Authenticate against Microsoft Exchange Server
     *                  Code based on information provided by
     *                  Matt Linzbach <MLinzbach@Merchant-Gould.com>.
     */
    function auth_exchange($user, $pass, $domain, $alias)
    {
	   if ($user == "") {  // Don't bother authenticating an empty username
		return false;      // ticket #335
	   }
    	global $dbh;
    	global $auth_exchange_params;
    	global $auth_exchange_only_one_domain;
    	global $auth_exchange_nt_domain;

        if ($auth_exchange_only_one_domain || empty($domain)) {
            $domain = $auth_exchange_nt_domain;
        }
        $connect = str_replace("%%NTDOMAIN%%", $domain, $auth_exchange_params);
        $connect = str_replace("%%USER%%", $user, $connect);
        $connect = str_replace("%%ALIAS%%", $alias, $connect);

        $mbox = imap_open($connect, $user, $pass);

        if ($mbox === false) {
            return false;
        } else {
      	    $ignored = imap_errors(); // ignore "mailbox is empty" notice
            imap_close($mbox);
            return true;
        }
    }


    /*
     * auth_sql(): Authenticate against an SQL database.
     */
    function auth_sql($user, $pass)
    {
	   if ($user == "") {  // Don't bother authenticating an empty username
		return false;      // ticket #335
	   }
        global $dbh;
        global $lang;
        global $auth_sql_dsn;
        global $auth_sql_table;
        global $auth_sql_username_column;
        global $auth_sql_password_column;
        global $auth_sql_email_column;
        global $auth_sql_password_type;
        global $auth_sql_connect_array;

        if (! isset($auth_sql_connect_array)) {
          $auth_sql_connect_array = array();
        }

        $auth_dbh = MDB2::connect($auth_sql_dsn, $auth_sql_connect_array);
        if (MDB2::isError($auth_dbh)) {
            return $auth_dbh; //return error for xlogin to process
        }
        $auth_dbh->setFetchMode(MDB2_FETCHMODE_ASSOC);

        $select = "SELECT " . $auth_sql_password_column . ", " . $auth_sql_email_column . " FROM " .
                  $auth_sql_table . " WHERE " . $auth_sql_username_column . " = ?";

        $email = "";
        $auth_sth = $auth_dbh->query($select, array($user));
        $auth_sth = $auth_dbh->prepare($select);
        $auth_res = $auth_sth->execute($user);
	if (PEAR::isError($auth_sth)) {
            die($auth_sth->getMessage());
        }

        if ($row = $auth_res->fetchRow())
        {
            $dbpass = $row[$auth_sql_password_column];
            $email = $row[$auth_sql_email_column];
        }
        $auth_sth->free();

        if (!empty($email) && !empty($dbpass)) {
            if ($auth_sql_password_type == "md5") {
                if (substr(md5($pass), 0, strlen($dbpass)) == $dbpass) {
	            return $email;
                }
            } elseif ($auth_sql_password_type == "crypt") {
	        if (($dbpass == "**" . $pass) || (crypt($pass, $dbpass) == $dbpass)) {
		    return $email;
		}
            } else { // plaintext
                if ($dbpass == $pass) {
		    return $email;
		}
            }
        }
        return false;
    }


    /*
     * auth_internal(): Authenticate against Maia's internal SQL database.
     */
    function auth_internal($user, $pass)
    {
	if ($user == "") {  // Don't bother authenticating an empty username
	    return false;      // ticket #335
	}
        global $dbh;
        require_once('maia_db/scrypt.php');

        $email = "";
        $testpass = md5($pass);
        $sth = $dbh->prepare("SELECT users.email, maia_users.password " .
                  "FROM users, maia_users " .
                  "WHERE users.id = maia_users.primary_email_id " .
                  "AND maia_users.user_name = ? "); 
        if (PEAR::isError($sth)) {
            die($sth->getMessage());
        }

        $res = $sth->execute(array($user));
        if (PEAR::isError($sth)) {
            die($sth->getMessage());
        }

        if ($row = $res->fetchrow()) {
            $email = $row["email"];
            $userpass = $row["password"];
        }
        $sth->free();

        if (empty($email)) {
            return false;
        }
        if(strlen($userpass) == 32) {
            // legacy password
            if($userpass === $testpass) {
                return $email;
            } else {
                return false;
            }
        }
        // Only reached if scrypt password
        if(Password::check($pass, $userpass)) {
            return $email;
        } else {
            return false;
        }

    }


    /*
     * auth(): Main authentication routine.
     */
    function auth($user_name, $pwd, $email, $nt_domain)
    {
    	global $dbh;
    	global $auth_method;
    	global $routing_domain;
    	global $address_rewriting_type;

        $authenticated = false;
        $user_name = trim(stripslashes($user_name));
        $email = trim($email);

        // Don't allow logins for domain-class pseudo-users
        if ((!empty($user_name) && $user_name[0] == "@") || (!empty($email) && $email[0] == "@")) {
            return array(false, false);
        }

        $pwd = stripslashes($pwd);
        if ($auth_method == "pop3") {
            if (!empty($routing_domain)) {
                if (!empty($user_name) && !empty($pwd)) {
                    $authenticated = auth_pop3($user_name, $pwd);
                    $email = $user_name . "@" . $routing_domain;
                }
            } else {
       	        if (!empty($email) && !empty($pwd)) {
                    $user_name = get_user_from_email($email);
                    $authenticated = auth_pop3($user_name, $pwd);
                }
            }
        } elseif ($auth_method == "imap") {
            if (!empty($email) && !empty($pwd)) {
                $email = get_rewritten_email_address($email, $address_rewriting_type);
                if ($address_rewriting_type == 4) {
                    $user_name = $email;
                } else {
                    $user_name = get_user_from_email($email);
                }
                $authenticated = auth_imap($user_name, $pwd);
            }
        } elseif ($auth_method == "ldap") {
            if (!empty($user_name) && !empty($pwd)) {
                $email = auth_ldap($user_name, $pwd);
                $authenticated = (!($email === false));
            }
        } elseif ($auth_method == "exchange") {
            if (!empty($user_name) && !empty($pwd)) {
                $authenticated = auth_exchange($user_name, $pwd, $nt_domain);
                // BROKEN!  No idea what e-mail address to return here.
            }
        } elseif ($auth_method == "sql") {
            if (!empty($user_name) && !empty($pwd)) {
                $email = auth_sql($user_name, $pwd);
		if (PEAR::isError($email)) {
			$authenticated = false;
		} else {
 	               $authenticated = (!($email === false));
		}
            }
        } elseif ($auth_method == "internal") {
            if (!empty($user_name) && !empty($pwd)) {
                $email = auth_internal($user_name, $pwd);
                $authenticated = (!($email === false));
            }
        } elseif ($auth_method == "external") {
           if (!empty($user_name) && !empty($pwd)) {
              $authenticated = auth_external($user_name,$pwd);
              $email = $user_name;
           }
        }

    	return array($authenticated, $email);
    }
?>
