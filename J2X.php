<?php
defined('_JEXEC') or die('Restricted access');
jimport('joomla.plugin.plugin');
$basepath = JPATH_ADMINISTRATOR . '/components/com_J2X';
require_once ('params.php');


class plgSystemJ2x extends JPlugin
{
    protected $execute = true;
    protected $request;

    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $execute_admin = $this
            ->params
            ->get('execute_admin', 0);

        if (empty($execute_admin) and JFactory::getApplication()->isAdmin())
        {
            $this->execute = false;
        }
    }


    function plgSystemCanonicalization(&$subject, $config)
    {
        $this->_done = array(
            'html' => array(
                'generator' => false,
                'robots' => false
            ) ,
            'feed' => array(
                'generator' => false
            )
        );
    }


    function onAfterDispatch()
    {
        $J2X_C = new J2X_C();
        $app = JFactory::getApplication();
        $document = JFactory::getDocument();

        if ($app->isAdmin())
        {
            $this->checkadmin();
        }
        else
        {
            $this->checksiteuserlogin();
            if (in_array($document->getType() , array(
                'html',
                'feed'
            )))
            {
                $document->setGenerator($J2X_C->generator);
            }
        }
    }

    function checksiteuserlogin()
    {
        $config = new JConfig();
        $J2X_C = new J2X_C();
        $app = JFactory::getApplication();
        $path = '';
        $path .= $J2X_C->options == 1 ? JURI::root() . $J2X_C->custom_path : JURI::root();
        $J2X = new J2X();
        $publish = $J2X_C->com_users_publish;

        if (!$publish)
        {
            return true;
        }

        $session = JFactory::getSession();
        $checkedKey = $session->get('J2XAuthentication');
        $passkey = $J2X_C->key;
        $task = $J2X_C->passkeytype;

        switch ($task)
        {
            case 'url':
            default:
                $resultUrlKey = J2X::checkSiteUrlKey();
                if (!empty($resultUrlKey))
                {
                    
                }
                else
                {
                    // echo "redirect";
                    $app->redirect($path);
                }
            break;
        }
    }

    function checkadmin()
    {
        $J2X_C = new J2X_C();
        $config = new JConfig();
        $app = JFactory::getApplication();
        $path = '';
        $path .= $J2X_C->options == 1 ? JURI::root() . $J2X_C->custom_path : JURI::root();
        $J2X = new J2X();

        $session = JFactory::getSession();
        $checkedKey = $session->get('J2XAuthentication');

        if (!empty($checkedKey))
        {
            return true;
        }

        $submit = JRequest::getVar('submit', '');
        $passkey = $J2X_C->key;
        $task = $J2X_C->passkeytype;

        switch ($task)
        {
            case 'url':
            default:
                $resultUrlKey = J2X::checkUrlKey($J2X_C);
                if (!empty($resultUrlKey))
                {
                    $session->set('J2XAuthentication', 1);
                    return true;
                }
                else
                {
                    $app->redirect($path);
                }
            break;
        }

    }

    public function onAfterRender()
    {
        $J2X_C = new J2X_C();
        $app = JFactory::getApplication();
        $db = JFactory::getDBO();
        if ($app->getName() != 'site')
        {
            return true;
        }

        $templatefolder = $J2X_C->template;
        $componentfolder = $J2X_C->components;
        $modulesfolder = $J2X_C->modules;
        $pluginsfolder = $J2X_C->plugins;
        $mediafolder = $J2X_C->media;

        // Replace src links
        $base = JUri::base(true) . '/';
        $buffer = JResponse::getBody();

        $buffer = $this->replacefolder('media', $buffer);
        $buffer = $this->replacefolder('templates', $buffer);
        $buffer = $this->replacefolder('plugins', $buffer);
        $buffer = $this->replacefolder('modules', $buffer);
        $buffer = $this->replacefolder('components', $buffer);
        /************SDX_J2X-EOR************/
        if (!empty($this->execute))
        {
            $search_replace = json_decode($this
                ->params
                ->get('search_replace') , true);
            if (!empty($search_replace['search']))
            {
                foreach ($search_replace['search'] as $key => $item)
                {
                    if (!empty($search_replace['execution'][$key])) if (!$this->checkExecutionStatus($search_replace['execution'][$key])) continue;
                    $limit = 1;
                    if (!empty($search_replace['replace_all'][$key])) $limit = - 1;
                    $pattern = '@' . $item . '@';
                    if (!empty($search_replace['replace_caseless'][$key])) $pattern .= 'i';
                    $pattern .= 'sU';
                    if (empty($search_replace['replace_regex'][$key])) $pattern = preg_quote($pattern);
                    $buffer = preg_replace($pattern, $search_replace['replace'][$key], $buffer, $limit);
                }
            }
        }
        /********* Remove EOL (Empty Space)*/
        $search = array(
            '/[^\S ]+/s',
            '/<!--(.|\s)*?-->/'
        );
        $replace = array(
            "",
            ""
        );
        /////////////////////////////////////
        $tag_start = "<script>";
        $tag_end = "<\/script>";
        $contents = $buffer;
        $output = "";
        $regexp = '/(.*?)' . $tag_start . '\s+(.*?)' . $tag_end . '(.*)/s';
        $found = preg_match($regexp, $contents, $matches);

        while ($found)
        {
            $output .= $matches[1];
            $tag_c = $matches[2];
            ob_start();
            echo '<script>' . $this->No_Comment($tag_c) . '</script>';
            $output .= ob_get_contents();
            ob_end_clean();
            $contents = $matches[3];
            $found = preg_match($regexp, $contents, $matches);

        }
        $output .= $contents;
        $search = array(
            '/[^\S ]+/s',
            '/<!--(.|\s)*?-->/'
        );
        $replace = array(
            "",
            ""
        );
        $buffer = preg_replace($search, $replace, $output);

        JResponse::setBody($buffer);
        return true;
    }

    function No_Comment($input)
    {
        $pattern = '/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\')\/\/.*))/';
        return preg_replace($pattern, '', $input);
    }

    function replacefolder($folder, $buffer)
    {
        $regex = '#' . $folder . '/(.*?)/(.*?)#';
        $buffer = preg_replace_callback($regex, array(
            'plgSystemJ2X',
            'faketemplate'
        ) , $buffer);
        $this->checkBuffer($buffer);
        return $buffer;

    }

    private function checkBuffer($buffer)
    {
        if ($buffer === null)
        {
            switch (preg_last_error())
            {
                case PREG_BACKTRACK_LIMIT_ERROR:
                    $message = "PHP regular expression limit reached (pcre.backtrack_limit)";
                break;
                case PREG_RECURSION_LIMIT_ERROR:
                    $message = "PHP regular expression limit reached (pcre.recursion_limit)";
                break;
                case PREG_BAD_UTF8_ERROR:
                    $message = "Bad UTF8 passed to PCRE function";
                break;
                default:
                    $message = "Unknown PCRE error calling PCRE function";
            }
            throw new RuntimeException($message);
        }
    }

    protected static function route(&$matches)
    {
        $url = $matches[1];
        $url = str_replace('&amp;', '&', $url);
        $route = JRoute::_('index.php?' . $url);
        return 'href="' . $route;
    }

    function faketemplate(&$matches)
    {
        $J2X_C = new J2X_C();
        $templ = explode("/", $matches[0]);
        if ($templ[0] == 'templates')
        {
            $template = $J2X_C->template_publish;
            $templatefolder = $J2X_C->template;
        }
        if ($templ[0] == 'components')
        {
            $templatefolder = $J2X_C->components;
        }
        if ($templ[0] == 'plugins')
        {
            $templatefolder = $J2X_C->plugins;

        }
        if ($templ[0] == 'modules')
        {
            $templatefolder = $J2X_C->modules;
        }
        if ($templ[0] == 'media')
        {
            $templatefolder = $J2X_C->media;
        }

        $fakefolder = $this->checkext($templ[0], $templ[1], $templatefolder);
        $faketemplate = $templatefolder . '/' . $this->checkext($templ[0], $templ[1], $templatefolder) . '/';
        $ext = $templ[0];
        $original = $templ[1];
        $this->htaccessfilesave($ext, $original, $templatefolder, $fakefolder);
        return $faketemplate;
    }

    // assing ne fake url to extensions
    function checkext($ext, $original, $dinamicfolder)
    {
        $db = JFactory::getDBO();
        $db->setQuery('SELECT * FROM #__ext_urls WHERE ext="' . $ext . '" AND original ="' . $original . '" AND alias = "' . $dinamicfolder . '"');
        $fake = $db->loadObject();
        $query = $db->getQuery(true);
        if (empty($fake))
        {
            $fakename = $this->generateRandomString();
            $query->insert('#__ext_urls');
            $query->set($db->quoteName('ext') . '=' . $db->quote($ext));
            $query->set($db->quoteName('original') . '=' . $db->quote($original));
            $query->set($db->quoteName('fake') . '=' . $db->quote($fakename));
            $query->set($db->quoteName('alias') . '=' . $db->quote($dinamicfolder));
            $db->setQuery($query)->execute();

        }
        else
        {
            $fakename = $fake->fake;
        }
        return $fakename;
    }

    function generateRandomString()
    {
        return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz") , 0, 5);
    }

    function htaccessfilesave($ext, $originalfolder, $templatefolder, $fakefolder)
    {
        $fakefolderpattern = $fakefolder;
        $originalfolder = $ext . '/' . $originalfolder;
        $fakefolder = $templatefolder . '/' . $fakefolder;
        $file = JPATH_ROOT . '/.htaccess';
        $dest = JPATH_ROOT . '/htaccess.old';

        if ((JFile::exists($file) == false) || ((JFile::exists($file) == true) && (JFile::read($file) == '')))
        {
            $buffer = "\nOptions +FollowSymLinks\n
                                            RewriteEngine On\n
                                            RewriteCond %{THE_REQUEST} ^GET\ /" . $originalfolder . "/ \n
                                            RewriteRule ^" . $originalfolder . "/(.*)$ $fakefolder/$1 [R,L] \n
                                            RewriteRule ^" . $fakefolder . "/(.*)$ " . $originalfolder . "/$1\n";
            JFile::write($file, $buffer);
            JFile::copy($file, $dest);
        }
        else
        {
            $buffer = JFile::read($file);
            $pattern = '/' . $fakefolderpattern . '/i';
            preg_match($pattern, $buffer, $matches);
            if (empty($matches[0]))
            {
                $buffer1 = "\n
											RewriteCond %{THE_REQUEST} ^GET\ /" . $originalfolder . "/ \n
											RewriteRule ^" . $originalfolder . "/(.*)$ $fakefolder/$1 [R,L] \n
											RewriteRule ^" . $fakefolder . "/(.*)$ " . $originalfolder . "/$1\n";

                $buffer2 = $buffer1 . $buffer;
                JFile::write($file, $buffer2);
                JFile::copy($file, $dest);
            }

        }

    }

    public function onAfterRoute()
    {
        jimport('joomla.filesystem.file');
    }

    public function onBeforeRender()
    {
        if (!empty($this->execute))
        {
            $this->request = JFactory::getApplication()->input;
            $debug = $this
                ->params
                ->get('debug', 0);

            if (!empty($debug))
            {
                $debug_output = $this->getDebugInformation();
                JFactory::getApplication()
                    ->enqueueMessage(JTEXT::sprintf('PLG_SDX_J2X_EOR_DEBUGOUTPUT', $debug_output));
            }
        }
    }

    private function getDebugInformation()
    {
        $uri = JUri::getInstance();
        $debug_output = '';
        if (JFactory::getApplication()->isSite()) $debug_array = array_filter(JRouter::getInstance('site')->parse($uri));
        else
        {
            $debug_output = str_replace('&', ',', $uri->getQuery());
            if (empty($debug_output))
            {
                $debug_array['option'] = $this
                    ->request
                    ->getWord('option');
                $debug_array['view'] = $this
                    ->request
                    ->getWord('view');
                $debug_array['layout'] = $this
                    ->request
                    ->getWord('layout');
                $debug_array = array_filter($debug_array);
            }
        }

        if (!empty($debug_array))
        {
            $debug_output = array();
            foreach ($debug_array as $key => $value)
            {
                if (!empty($value)) $debug_output[] = $key . '=' . $value;
            }
            $debug_output = implode(',', $debug_output);
        }
        return $debug_output;
    }

}
/*************************************/
/******************CLS****************/
/*************************************/
class J2X
{
    public static function checkUrlKey($J2X_C)
    {
        $my = JFactory::getUser();
        if ((preg_match("/administrator\/*index.?\.php$/i", $_SERVER['PHP_SELF'])))
        {
            parse_str($_SERVER['QUERY_STRING'], $get_array);
            if (array_key_exists($J2X_C->keyword, $get_array)) if (!$my->id && $get_array[$J2X_C
                ->keyword] != $J2X_C->key)
            {
                return false;
            }
            else
            {
                return true;
            }
        }
    }

    public static function checkSiteUrlKey()
    {
        $my = JFactory::getUser();
        if ((preg_match("/index.?\.php$/i", $_SERVER['PHP_SELF'])))
        {
            parse_str($_SERVER['QUERY_STRING'], $get_array);
            if (!empty($get_array['option']))
            {
                if ($get_array['option'] == 'com_users') return false;
            }
            return true;
        }
        if (!empty($_GET['option']))
        {
            if ($_GET['option'] == 'com_users')
            {
                return false;
            }
        }
        return true;
    }
}

