<?php
/**
    Prails Web Framework
    Copyright (C) 2012  Robert Kunze

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/** Section Handler
 * 
 * Event handlers are a specific type of functionality that is part of a module.
 * To describe it with the MVC pattern, the event handlers are the controller part
 * that uses the data queries, resembling the model and uses the output code templates,
 * resembling the view in order to generate a whole page. 
 * 
 * So in an event handler one usually fetches data from the database using the data queries,
 * prepares it, and generates additional information that is needed for the output, 
 * and finally calls the output code in order to render the page. Therefore, this context
 * has certain special functions and variables.
 *
 * ![event handler with multiple endpoints](static/images/doc/multiple-endpoints.png) An event 
 * handler usually consists of normal PHP code. It can have multiple so-called "endpoints", 
 * which are actually targets that are being used for receiving HTTP POST requests. A target 
 * will be used as soon as there is a variable POSTed to the event handler whose name is 
 * exactly the same as the target's name. Usually one would use a button name to decide which 
 * target to trigger. 
 *
 * ![event handler with multiple templates](static/images/doc/multiple-templates.png) Event handlers can also have multiple templates. This is especially useful for rendering 
 * alternative page contents depending on the state of the event handler or certain parameters
 * given to it. One such template could, for example, be used to render the contents of an email
 * that is being sent out, while the other one provides the form that is used to trigger the 
 * sending of that email.
 *
 * *Example:*
 *
 * Event handler code - default endpoint
 * {{{
 * $arr_param["topics"] = $data->listTopics();
 * return out($arr_param);
 * }}}
 *
 * Output code - default template
 * {{{
 * &lt;h2&gt;Contact Us&lt;/h2&gt;
 * &lt;form method="post" action="Base/contact"&gt;
 *    &lt;c:input name="topic" values="topics" type="select" label="Please select a topic:"/&gt;
 *    &lt;c:input type="text" multiple="5" name="message" label="Enter your message:" /&gt;
 *    &lt;button type="submit" name="send"&gt;Send Inquiry&lt;/button&gt;
 * &lt;/form&gt;
 * }}}
 *
 * Event handler code - "send" endpoint
 * {{{
 * $arr_param["message"] = $_POST['message'];
 * $arr_param['topic'] = $_POST['topic'];
 * $content = out($arr_param, "", "mail");
 * sendMail("service@example.org", "Inquiry", $content, "Example Service", "no-reply@example.org");
 * }}}
 *
 * Output code - "mail" template
 * {{{
 * A new inquiry was received for topic #local.topic. The customer's message was:
 * #local.message
 * Cheers,
 * Your Example Service
 * }}}
 *
 * The following variables are always defined in a handler:
 * - $arr_param (Array) - an array containing the context that was given to the current event handler. Normally, this is an empty array. In case you use the `[tools]invoke` method, however, you can pass a custom context as the second parameter.
 * - $data (Database) - the object that allows to access all data queries of the current module. Data queries from other modules cannot be accessed.
 * - $currentLang (Language) - a reference to the language library, that let's you get access to content assets.
 * - $SERVER (String) - the absolute URL to the page (without paths relative to the Prails directory)
 * - $SECURE_SERVER (String) - the absolute HTTPS URL to the page (without paths relative to the Prails directory)
 * 
 * In order to trigger generating the view, the following method exists:
 * out($arr_param[, $decorator[, $template]]) -> String
 * - $arr_param (Array) - the context that should be available to the template being run
 * - $decorator (String) - the event handler name of the decorator with which the output code should be decorated. This is supposed to be in the colon notation (`module:event`). Within that decorator the same context will be available as the one given to the output code.
 * - $template (String) - an identifier referencing the output code's template name that should be used. You can add new templates by using the "Add Template" button in the Prails IDE.
 * 
 **/ 
class AbstractHandler {
 	protected $obj_data = null;
 	protected $obj_print = null;
 	protected $obj_parent = null;
 	protected $obj_lang = null;
 	
 	protected $session = null;
 	
    /**
     * @desc calls the corresponding method in printer
     * @param $str_func [STRING]   function to call
     * @param $arr_param [ARRAY]   some data that may be needed
     * @returns [BOOLEAN]    TRUE if call successful, else FALSE
     */
    public function _callPrinter ($str_func, $arr_param, $decorator = "", $template = "")
    {
        if (method_exists($this->obj_print, $str_func))
        {
            return $this->obj_print->$str_func($arr_param, $decorator, $template);
        } else
        {
            pushError("Could not call ".$str_func." in Printer.");
            return false;
        }
    }
    
    public function registerEvents() {
        // empty method stub for registering any events
    }
    
    public function getSession() {
        if ($this->session == null) $this->session = new Session();
        return $this->session;
    }
 	
 	/**
 	 * retrieves the data object of this module
 	 *
 	 * @return Database		Database access object for this module
 	 */
 	public function getData() {
 		return $this->obj_data;
 	}
 	
 	/**
 	 * retrieves the parent module of this module
 	 *
 	 * @return	AbstractHandler	parent module handler
 	 */
 	public function getParent() {
 		return $this->obj_parent;
 	}
 }
 
?>
