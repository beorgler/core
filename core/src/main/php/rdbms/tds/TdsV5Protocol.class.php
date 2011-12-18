<?php
/* This class is part of the XP framework
 *
 * $Id$ 
 */

  uses('rdbms.tds.TdsProtocol');

  /**
   * TDS V5 protocol implementation
   *
   * @see   https://github.com/mono/mono/blob/master/mcs/class/Mono.Data.Tds/Mono.Data.Tds.Protocol/Tds50.cs
   */
  class TdsV5Protocol extends TdsProtocol {
  
    static function __static() { }
  
    /**
     * Setup record handlers
     *
     * @see     http://infocenter.sybase.com/help/topic/com.sybase.dc35823_1500/html/uconfig/uconfig111.htm
     * @see     http://infocenter.sybase.com/help/topic/com.sybase.dc38421_1500/html/ntconfig/ntconfig80.htm
     * @return  [:rdbms.tds.TdsRecord] handlers
     */
    protected function setupRecords() {
      $records[self::T_NUMERIC]= newinstance('rdbms.tds.TdsRecord', array(), '{
        public function unmarshal($stream, $field) {
          if (-1 === ($len= $stream->getByte()- 1)) return NULL;
          $pos= $stream->getByte();
          $bytes= $stream->read($len);
          if ($i= ($len % 4)) {
            $bytes= str_repeat("\0", 4 - $i).$bytes;
            $len+= 4 - $i;
          }
          for ($n= 0, $m= $pos ? -1 : 1, $i= $len- 4; $i >= 0; $i-= 4, $m= bcmul($m, "4294967296", 0)) {
            $n= bcadd($n, bcmul(sprintf("%u", current(unpack("N", substr($bytes, $i, 4)))), $m, 0), 0);
          }
          return $this->toNumber($n, $field["scale"], $field["prec"]);
        }
      }');
      $records[self::T_DECIMAL]= $records[self::T_NUMERIC];
      $records[self::T_BINARY]= newinstance('rdbms.tds.TdsRecord', array(), '{
        public function unmarshal($stream, $field) {
          if (0 === ($len= $stream->getByte())) return NULL;
          $string= $stream->read($len);
          return iconv("cp850", "iso-8859-1", substr($string, 0, strcspn($string, "\0")));
        }
      }');
      $records[self::T_VARBINARY]= newinstance('rdbms.tds.TdsRecord', array(), '{
        public function unmarshal($stream, $field) {
          if (0 === ($len= $stream->getByte())) return NULL;
          return iconv("cp850", "iso-8859-1", $stream->read($len));
        }
      }');
      $records[self::T_LONGBINARY]= newinstance('rdbms.tds.TdsRecord', array(), '{
        public function unmarshal($stream, $field) {
          $len= $stream->getLong();
          return $stream->getString($len / 2);
        }
      }');
      return $records;
    }

    /**
     * Returns default packet size to use
     *
     * @return  int
     */
    protected function defaultPacketSize() {
      return 512;
    }

    /**
     * Connect
     *
     * @param   string user
     * @param   string password
     * @throws  io.IOException
     */
    protected function login($user, $password) {
      if (strlen($password) > 253) {
        throw new IllegalArgumentException('Password length must not exceed 253 bytes.');
      }

      $packetSize= (string)$this->defaultPacketSize();
      $packet= pack(
        'a30Ca30Ca30Ca30CCCCCCCCCCx7a30Ca30Cx2a253CCCCCa10CCCCCCCCa30CCnx8nCa30CCa6Cx8',
        'localhost', min(30, strlen('localhost')),
        $user, min(30, strlen($user)),
        $password, min(30, strlen($password)),
        (string)getmypid(), min(30, strlen(getmypid())),
        0x03,       // Byte order for 2 byte ints: 2 = <MSB, LSB>, 3 = <LSB, MSB>
        0x01,       // Byte order for 4 byte ints: 0 = <MSB, LSB>, 1 = <LSB, MSB>
        0x06,       // Character rep (6 = ASCII, 7 = EBCDIC)
        0x0A,       // Eight byte floating point rep (10 =  IEEE <LSB, ..., MSB>)
        0x09,       // Eight byte date format (8 = <MSB, ..., LSB>)
        0x01,       // Notify of "use db"
        0x01,       // Disallow dump/load and bulk insert
        0x00,       // SQL Interface type
        0x00,       // Type of network connection
        $this->getClassName(), min(30, strlen($this->getClassName())),
        'localhost', min(30, strlen('localhost')),
        $password, strlen($password)+ 2,    // Remote passwords
        0x05, 0x00, 0x00, 0x00,             // TDS Version
        'Ct-Library', strlen('Ct-Library'), // Client library name
        0x06, 0x00, 0x00, 0x00,             // Prog version
        0x00,                               // Auto convert short
        0x0D,                               // Type of flt4
        0x11,                               // Type of date4
        'us_english', strlen('us_english'), // Language
        0x00,                               // Notify on lang change
        0x00,                               // Security label hierarchy
        0x00,                               // Security spare
        0x00,                               // Security login role
        'iso_1', strlen('iso_1'),           // Charset
        0x01,                               // Notify on charset change
        $packetSize, strlen($packetSize)    // Network packet size (in text!)
      );

      // Capabilities
      $capabilities= pack(
        'CnCCCCCCCCCCCCCCCCCC',
        0xE2,                               // TDS_CAPABILITY_TOKEN
        20,
        0x01,                               // TDS_CAP_REQUEST
        0x03, 0xEF, 0x65, 0x41, 0xFF, 0xFF, 0xFF, 0xD6,
        0x02,                               // TDS_CAP_RESPONSE
        0x00, 0x00, 0x00, 0x06, 0x48, 0x00, 0x00, 0x08
      );

      // Login
      $this->stream->write(self::MSG_LOGIN, $packet.$capabilities);
    }
    
    /**
     * Issues a query and returns the results
     *
     * @param   string sql
     * @return  var
     */
    public function query($sql) {
      $this->stream->write(self::MSG_QUERY, $sql);
      $token= $this->read();

      if ("\xEE" === $token) {          // TDS_ROWFMT
        $fields= array();
        $this->stream->getShort();
        $nfields= $this->stream->getShort();
        for ($i= 0; $i < $nfields; $i++) {
          $field= array();
          if (0 === ($len= $this->stream->getByte())) {
            $field= array('name' => NULL);
          } else {
            $field= array('name' => $this->stream->read($len));
          }
          $field['status']= $this->stream->getByte();
          $this->stream->read(4);   // Skip usertype
          $field['type']= $this->stream->getByte();

          // Handle column.
          if (self::T_TEXT === $field['type'] || self::T_IMAGE === $field['type']) {
            $field['size']= $this->stream->getLong();
            $this->stream->read($this->stream->getShort());
          } else if (self::T_NUMERIC === $field['type'] || self::T_DECIMAL === $field['type']) {
            $field['size']= $this->stream->getByte();
            $field['prec']= $this->stream->getByte();
            $field['scale']= $this->stream->getByte();
          } else if (self::T_LONGBINARY === $field['type'] || self::XT_CHAR === $field['type']) {
            $field['size']= $this->stream->getLong() / 2;
          } else if (isset(self::$fixed[$field['type']])) {
            $field['size']= self::$fixed[$field['type']];
          } else {
            $field['size']= $this->stream->getByte();
          }

          $this->stream->read(1);   // Skip locale
          $fields[]= $field;
        }
        return $fields;
      } else if ("\xFD" === $token) {   // DONE
        $meta= $this->stream->get('vstatus/vcmd/Vrowcount', 8);
        $this->done= TRUE;
        // TODO: Maybe?
        return $meta['rowcount'];
      } else if ("\xE3" === $token) {   // ENVCHANGE, e.g. from "use [db]" queries
        // HANDLE!
        $this->cancel();
      } else {
        throw new TdsProtocolException(
          sprintf('Unexpected token 0x%02X', ord($token)),
          0,    // Number
          0,    // State
          0,    // Class
          NULL, // Server
          NULL, // Proc
          -1    // Line
        );
      }
    }
  }
?>
