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
class SMTP { 
    
    private $server;
    private $port;
    private $user;
    private $pass;
    private $socket;
    
    function SMTP($server = "localhost", $port=25, $user = "", $pass = "") {
        $this->server = $server;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }
    
    function mail($to, $subject, $message, $headers = "") {
        global $log;
        $message = preg_replace("/(?<!\r)\n/si", "\r\n", $message);
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (!is_numeric($key)) {
                    $header_string .= ucfirst($key).": ".$value."\r\n";
                } else {
                    $header_string .= $value."\r\n";
                }
            }
        } else {
            $header_string = $headers;
            $hlist = explode("\r\n", $headers);
            $headers = Array();
            foreach ($hlist as $line) {
                $pair = explode(": ", $line);
                $headers[$pair[0]] = $pair[1];
            }
        }
        $header_string = trim($header_string);
        
        if (!$this->socket = @fsockopen($this->server, $this->port, $errno, $errstr, 20)) {
            $log->error("Unable to connect to server ".$this->server." on port ".$this->port.".");
            return false;
        }
        
        $this->parse("220");
        
        if (!empty($this->user) && !empty($this->pass))  {
            $this->send("EHLO " . $this->server); 
            $this->parse("250"); 
    
            $this->send("AUTH LOGIN\r\n"); 
            $this->parse("334"); 
            $this->send(base64_encode($this->user)); 
            $this->parse("334"); 
            $this->send(base64_encode($this->pass)); 
            $this->parse("235"); 
        } else {
            $this->send("HELO " . $this->server); 
            $this->parse("250"); 
        }
        
        $from = ini_get(sendmail_from);
        $this->send("MAIL FROM: " . if_set($from, $headers["From"])); 
        if (!$this->parse("250")) return false; 
        
        $this->send("RCPT TO: ".$to); 
        if (!$this->parse("250")) return false; 

        $this->send("DATA"); 
        if (!$this->parse("354")) return false; 

        $this->send("Subject: ".$subject);
        $this->send("Date: ".date('r')); 
        $this->send("To: ".$to); 
        $this->send("X-Mailer: ".PROJECT_NAME." Mail Service V".phpversion()); 
        $this->send($header_string."\r\n"); 
        $this->send($message); 
        $this->send("."); 
        
        $success = $this->parse("250"); 
        $this->send("QUIT"); 
        fclose($socket); 

        return $success;        
    }
    
    function parse($response) { 
        global $log;
        do { 
            if (!($server_response = @fgets($this->socket, 256))) { 
                $log->error("Couldn't get mail server response code."); 
            } 
        } while (substr($server_response, 3, 1) != " ");

        if (substr($server_response, 0, 3) != $response) { 
            $log->error("Ran into problems sending Mail. Response: $server_response"); 
            return false;
        } 
        
		$log->trace("< ".$server_response);
        
        return true;
    }
    
    function send($message) {
        global $log;
		$log->trace("> ".$message);
        fputs($this->socket, $message."\r\n");
    }
}

function fmail($to, $subject, $content, $headers) {
	if (USE_SMTP === true) {
	    $smtp = new SMTP(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD);
	    return $smtp->mail($to, $subject, $content, $headers);
	} else 
		return @mail($to, $subject, $content, $headers);
}
?>