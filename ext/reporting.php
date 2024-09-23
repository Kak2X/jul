<?php
// HOLY SHIT AN IRC BOT WORKING OUT OF THE BOX FOR ACMLMBOARD
// WHAT IS THIS MAGIC NOBODY EVER DID IN FUCKING 22 YEARS
// (you're welcome, long after the fact it's relevant -- Kak)

if (substr(php_sapi_name(), 0, 3) != 'cli') {
	die("Command-line only.");
}

const POWER_PREFIXES = ['', '+','%','@','&','~'];
const POWER_CHARS = ['', 'v','h','o','a','?'];

const E_DISCONNECTED = -1;
const USRCMD_OK = 0;
const USRCMD_QUIT = 1;
const USRCMD_REBOOT = 2;

print "[BOT] HELLO".PHP_EOL;

// Get irc credentials and channel settings
require "../lib/mysql.php";
require "../lib/defines.php";

while (true) {	
	print "[BOT] Looking up settings".PHP_EOL;
	// Here so the config can be reloaded, if needed
	require "../lib/config.php";
	// Get the channel list off the board db, then close the connection since we don't need it anymore
	$sql = new mysql();
	$sql->connect($sqlhost, $sqluser, $sqlpass, $dbname) or	die("Couldn't connect to the MySQL server.");
	$settings = $sql->fetchq("SELECT * FROM irc_settings");
	$channels = $sql->fetchq("SELECT id, name, chankey FROM irc_channels", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
	$sql->connection = null;
	$sql = null;
	
	print "[BOT] The operator nick(s) will be \"{$settings['opnick']}\"".PHP_EOL;
	$ops = explode(",", $settings['opnick']);
	print "[BOT] Opening receiver socket on port {$settings['recvport']}".PHP_EOL;
	$recv = new listener($settings['recvport']);
	if (!$recv->conn) {
		print "[BOT] Failed to create the socket. Waiting 10 seconds, then retrying.".PHP_EOL;
		sleep(10);
		continue;
	}
	
	// Do not allow proceeding until we connect successfully to IRC.
	// The retry reloads the configuration just in case it's invalid, to allow getting any updated and hopefully fixed values.
	print "[BOT] Connecting to IRC server at {$settings['server']}:{$settings['port']} with nick \"{$settings['nick']}\"".PHP_EOL;
	$irc = new irc($settings['server'], $settings['port'], $settings['nick'], $settings['pass'], $channels);
	
	if (!$irc->conn) {
		print "[BOT] Failed to connect. Waiting 10 seconds, then retrying.".PHP_EOL;
		sleep(10);
		continue;
	}
	
	// Build the list of sockets to handle that didn't fail to open
	$socket_read = [];
	if ($recv->conn)
		$socket_read[] = $recv->conn;
	if ($irc->conn) {
		$socket_read[] = $irc->conn;
	}
	// values we don't care of, but have to pass to socket_select
	$_no_write = $_no_except = null;
	
	print "[BOT] Waiting for messages.".PHP_EOL;
	while (true) {
		
		// Sleep while waiting for any message, which modifies $socket_read_cur
		$socket_read_cur = $socket_read;
		socket_select($socket_read_cur, $_no_write, $_no_except, 1);
		
		// Handle the IRC bot, if triggered
		if (in_array($irc->conn, $socket_read_cur, true)) {
			$ircmsg = $irc->getMessages();
			// If this socket ever closes itself, restart the whole thing (so we won't have to manually fudge with $socket_read)
			if ($ircmsg === E_DISCONNECTED) {
				print "[BOT] Disconnected, attempting to reconnect.".PHP_EOL;
				$recv->quit();
				break;
			} else if ($ircmsg && ($usrres = do_bot($ircmsg))) {
				switch ($usrres) {
					case USRCMD_QUIT:
						die("End of stream.");
					case USRCMD_REBOOT:
						$recv->quit();
						break 2;
				}
			}
		}

		// Retrieve messages to send to IRC, if triggered
		if (in_array($recv->conn, $socket_read_cur, true)) {
			$request = $recv->getMessage();
			
			// Negative channel IDs are special commands sent by the board
			// For now there's only a restart command used when refreshing the configuration.
			switch ($request[0]) {
				case BOTCMD_RESTART:
					//print "[BOT] Received restart command from board.".PHP_EOL;
					$irc->quit($request[1]);
					$recv->quit();
					break 2;
				default:
					if (isset($channels[$request[0]])) {
						$chan = $channels[$request[0]];
						// debugprint!
						//print "[BOT] Received privmsg request for channel #{$request[0]} ({$chan['name']}):".PHP_EOL;
						$irc->sendMessage($chan['name'], $request[1]);
					} else {
						//print "[BOT] Received bad privmsg request for invalid channel #{$request[0]}".PHP_EOL;
					}
					
					break;
			}
		}

	}
	
	print "[BOT] Now reloading!".PHP_EOL;
}
	
// Custom command handler, with basic template commands that are otherwise worthless
function do_bot($ircmsg) {
	global $irc, $ops;
	
	foreach ($ircmsg as $msg) {
		print "<".POWER_PREFIXES[$msg->user->power]."{$msg->user->name}> {$msg->msg}".PHP_EOL;
		
		if ($msg->msg[0] === "!") {
			
			// Perm check by whitelisted name
			if (in_array($msg->user->name, $ops, true)) {
				if (strpos($msg->msg, "!quit") === 0) {
					$irc->quit("End of stream.");
					return USRCMD_QUIT;
				}
				if (strpos($msg->msg, "!reboot") === 0) {
					$irc->quit("User-signaled reboot.");
					return USRCMD_REBOOT;
				}
			}
			// Perm check by IRC role
			if ($msg->user->power >= 1) { // at least voice
				if (strpos($msg->msg, "!say ") === 0) {
					$irc->sendMessage($msg->chan, substr($msg->msg, 5));
				}
			}

			if (strpos($msg->msg, "!mypow") === 0) {
				$irc->sendMessage($msg->chan, "User '{$msg->user->name}' is '".POWER_PREFIXES[$msg->user->power]."' ({$msg->user->power})");
			}
		}
	}
	
	return USRCMD_OK;
}

// handles listening to the board writing into the socket.
class listener {
	public $conn;
	
	public function __construct($port) {
		// Create the server
		$this->conn = socket_create_listen($port);
		if ($this->conn === false) {
			trigger_error("Could not create socket for listening to reporting requests on port {$port}", E_USER_WARNING);
			return;
		}
	}
	
	public function getMessage() {
		
		// Get the message off the queue
		$client = socket_accept($this->conn);
		if ($client === false)
			return null;
		
		// Read out what the board sent us and immediately close it
		$res = socket_read($client, 256);
		socket_close($client);
		
		// Just in case we got sent nothing
		if (!$res) 
			return null;
		
		// Split the received string in the format <channel id>|<formatted message>
		$parts = explode("|", $res, 2);
		
		if (count($parts) !== 2)
			return null;
		
		return $parts;
	}
	
	public function quit() {
		socket_close($this->conn);
	}
}


// base irc client for handling a single server
class irc {
	// Socket connection
	public $conn;
	
	// Connection settings
	private $host;
	private $port;
	private $names;
	private $password;
	private $autojoin;
	
	// Currently connected channels
	private $channels = [];
	// Channels to join at some later point
	private $channel_join_queue = [];
	
	// Nickname autocycler
	private $name_id = -1;
	private $nickname;
	
	public function __construct($host, $port, $name, $password, $autojoin = []) {
		// Save these to allow automatically reconnecting without having to pass them again
		$this->host = $host;
		$this->port = $port;
		$this->names = explode(",", $name);
		$this->nextNick();
		$this->password = $password;
		$this->autojoin = $autojoin;
		// Actually connect now
		$this->connect();
	}
	
	private function connect() {
		$this->conn = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if (!socket_connect($this->conn, $this->host, $this->port)) {
			trigger_error("Could not connect to the IRC server {$this->host}:{$this->port}", E_USER_WARNING);
			$this->conn = null;
			return;
		}
		
		// Connection identifier...
		$cnpass = "KB2".sha1(dechex(mt_rand(0, PHP_INT_MAX)));
		
		// Init channel list
		$this->channels = [];
		$this->channel_join_queue = $this->autojoin;
		
		// Perform the mandatory init commands
		$this->rawSendMessage("PASS {$cnpass}");
		$this->rawSendMessage("NICK {$this->nickname}");
		$this->rawSendMessage("USER {$this->nickname} 0 * :KakBotV2");	
	}
	
	/* 
	disabled for being annoying to work with with socket_select
	// Hard reconnect
	public function reconnect() {
		if ($this->conn)
			socket_close($this->conn);
		$this->connect();
	}*/
	
	public function quit($msg = "Exiting") {
		$this->rawSendMessage("QUIT :$msg");
	}
	

	
	public function join($channel, $key = "") {
		$this->rawSendMessage("JOIN $channel".($key ? " $key" : "").PHP_EOL);
		$this->channels[$channel] = new chandef($channel, $key); // >_>
		//print "[XXX] Names unlocked for $channel (Join)".PHP_EOL;
	}
	
	
		
	private function nextNick() {
		$this->name_id = ($this->name_id + 1) % count($this->names);
		$this->nickname = $this->names[$this->name_id];
		return $this->nickname;
	}
	
	public function getMessages() {
		// Get the array of lines off the queue
		$lines = $this->getRowsFromBuffer();
		
		// Return out error codes, if any
		if ($lines === E_DISCONNECTED)
			return $lines;
		
		$msgs = [];
		foreach ($lines as $line) {
			if (!$line) continue;
			
			print "[IN] $line".PHP_EOL;
			
			if ($line[0] === ":") {
				// example of what we're trying to parse:
				// :Kak!Kak@bdnk-[...].com KICK #y-staff KakBot2 :Kak
				// |###       $host       |####  |   $args     |  $msg
				// $user                  $code                 
				
				// :irc.badnik.zone 003 KakBot2 :This server was created 05:06:02 Jun 12 2016
				// |     $host     |###| $args | $msg
				//                $code          
				
				
				list ($host, $code, $rawdata) = explode(" ", substr($line, 1), 3);
				
				// Either server name or user host
				$user = null;
				if ($host !== $this->host) {	
					$user = substr($host, 0, strpos($host, "!"));		
				}
				
				// If there's a parameter separator in $rawdata, split that
				$args = $msg = null;
				$argp = strpos($rawdata, ":");
				if ($argp !== false) {
					$args = explode(" ", rtrim(substr($rawdata, 0, $argp))); // rtrim just in case there's no space before :
					$msg = substr($rawdata, $argp + 1);
				} else {
					$args = explode(" ", rtrim($rawdata));
				}
				
				
				switch ($code) {
					case '001':
						if ($this->channel_join_queue) {
							foreach ($this->channel_join_queue as $chan) {
								$this->join($chan['name'], $chan['chankey']);
							}
							$this->channel_join_queue = [];
						}
						break;
						
					case 437: // Nick/channel is temporarily unavailable
						if ($args[0] == "#") // ignore if it's a channel
							break;
					case 431: // No nickname given
					case 432: // Erroneous nickname
					case 433: // Nickname already existing
					case 436: // Nickname collision
						$this->setNick($this->nextNick());
						break;

					case 353: // NAMES
						// <username> <=> <channel> :<user list with power>
						if ($args[0] == $this->nickname && isset($this->channels[$args[2]])) {
							$chan = $this->channels[$args[2]];
							
							//--
							// Clear the userlist if it isn't locked.
							// This lock will be reset by the END OF NAMES command.
							if (!$chan->names_in_progress) { // lock == false?
								$chan->users = [];
								$chan->names_in_progress = true;
								//print "[XXX] Names locked for {$chan->name}".PHP_EOL;
							}
							//--
							
							// Parse the user list
							foreach (explode(" ", $msg) as $nuser) {
								// If the username is prefixed by a special symbol, separate it from the rest
								if (in_array($nuser[0], POWER_PREFIXES, true)) {
									$n_name = substr($nuser, 1);
									$n_power = $nuser[0];
								} else {
									$n_name = $nuser;
									$n_power = "";
								}
								$chan->users[$n_name] = new chanuser($n_name, $n_power);
							}
						}
						break;
						
					case 366: // END OF NAMES
						// <username> <channel> :<msg>.
						if ($args[0] == $this->nickname && isset($this->channels[$args[1]])) {
							//print "[XXX] Names unlocked for {$args[1]}".PHP_EOL;
							$this->channels[$args[1]]->names_in_progress = false;
						}						
						break;
						
					case 'JOIN':
						// :<channel name>
						if (isset($this->channels[$msg])) {
							$this->channels[$msg]->users[$user] = new chanuser($user);
						}
						break;
						
					case 'MODE':
						// <channel name> <[+/-]flags> <target user>
						
						// If the user exists on that channel list, update the power (if applicable)
						if (isset($args[2]) && isset($this->channels[$args[0]]->users[$args[2]])) {
							$nuser = $this->channels[$args[0]]->users[$args[2]];
							$npow  = $args[1];
							
							if ($npow[0] == "+") {
								for ($i = 1; $i < strlen($npow); ++$i)
									$nuser->addRole($npow[$i]);
							} else {
								for ($i = 1; $i < strlen($npow); ++$i)
									$nuser->remRole($npow[$i]);
							}
						}
						break;
						
					case 'KICK':
						// <channel> <kicked nick> :<msg>
						if ($args[1] == $this->nickname && isset($this->channels[$args[0]])) {
							// We got kicked, reset the channel info and autorejoin
							$chan = $this->channels[$args[0]];
							unset($this->channels[$args[0]]);
							$this->join($chan[0], $chan[1]);
						} else {
							// Remove other user from channel user list
							unset($this->channels[$args[0]]->users[$args[1]]);
						}
						break;
					case 'PRIVMSG':
						// <channel> :<msg>
						// <channel> could be also an user, in which case the isset will return false
						$out = new privmsg();
						$out->user = isset($this->channels[$args[0]]->users[$user]) ? $this->channels[$args[0]]->users[$user] : new chanuser($user);
						$out->chan = $args[0]; // "source" would be a more accurate term
						$out->msg  = $msg;
						
						$msgs[] = $out;
				}
			}
			else if ($line == "PING :{$this->host}") {
				// PING? PONG!
				$this->rawSendMessage("PONG {$this->nickname}");
			} 
			else if (strpos($line, "PING :") === 0) {
				// Weird login cookie thing, not on badnik
				$this->rawSendMessage("PONG :".substr($line, 6));
			} 
			else if (strpos($line, "ERROR ") === 0) {	
				list($_, $errdesc, $data) = explode(":", $line, 3);
				if ($errdesc == "Closing link")
					return E_DISCONNECTED;
			} else {
				//print "[XXX] Unrecognized command above.".PHP_EOL;
			}
			
		}
		return $msgs;
	}
	
	public function rawSendMessage($msg) {
		print "[OUT] $msg".PHP_EOL;
		socket_write($this->conn, $msg."\r\n");
	}
	
	public function sendMessage($channel, $msg) {
		$this->rawSendMessage("PRIVMSG $channel :$msg");
	}
	
	public function setNick($nick) {
		$this->rawSendMessage("NICK {$nick}");
	}
	
	private $buffer = "";
	private function getRowsFromBuffer() {
		// Add to the string buffer we're keeping
		$buf = socket_read($this->conn, 4096);
		if ($buf === false) return E_DISCONNECTED;
		if ($buf) $this->buffer .= $buf;
		//--
		if (!$this->buffer) return [];
	
		$all_lines = explode("\n", $this->buffer);
		// Detect if the last line is incomplete. If so, keep it in the buffer.
		// This works because messages always end in newlines, and splitting by "\n" should leave the last array elem blank.
		$last_elem = $all_lines[count($all_lines)-1];
		if (!$last_elem) {
			$this->buffer = "";
		} else {
			$this->buffer = $last_elem;
			array_pop($all_lines);
		}
		return array_map('trim', $all_lines);
	}
}

class chandef {
	// Channel name
	public $name;
	// Channel key
	public $key;
	// User list (chanuser[])
	public $users = [];
	// Status for NAMES command.
	// Needed because with enough users, the server will return multiple NAMES commands before the END OF NAMES,
	// but only the first one should wipe the existing names list.
	public $names_in_progress = false;
	
	public function __construct($name, $key = "") {
        $this->name = $name;
		$this->key = $key;
    }
}

class chanuser {
	public $name;
	public $power = 0;
	public function __construct($name = "", $power_symbol = "") { // +, %, @, ...
        $this->name = $name;
		if ($power_symbol) {
			$pow = array_search($power_symbol, POWER_PREFIXES, true);
			if ($pow !== false)
				$this->power = $pow;
		}
    }
	
	public function addRole($power_char) { // v, h, o, ...
		$newpow = array_search($power_char, POWER_CHARS, true);
		if ($newpow !== false && $newpow > $this->power) {
			$this->power = $newpow;
		}
	}
	public function remRole($power_char) { // v, h, o, ...
		$delpow = array_search($power_char, POWER_CHARS, true);
		if ($delpow !== false && $delpow >= $this->power) {
			$this->power = 0; // we don't keep track of multiple roles
		}
	}
	//public function __toString() {
    //    return $this->power.$this->user;
    //}
}

class privmsg {
	public $user;
	public $chan;
	public $msg;
}