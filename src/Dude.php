<?php
namespace diegotibi;

use Exception;
use PDO;

/**
 * Class Dude
 *
 * @author Diego Tibi - CereS
 */
class Dude
{
    /**
     * @var string[]
     */
    public static $labels = [
        'ff0001' => 'record_type',
        'fe0010' => 'name',
        '101f40' => 'ip_addresses',
        '101f41' => 'dns_names',
        '101f43' => 'dns_lookup_interval',
        '101f44' => 'mac_address',
        '101f46' => 'username',
        '101f47' => 'password',
        '101f4a' => 'router_os',
        '101f4c' => 'device_type_id',
        '101f57' => 'pid',
        '101f58' => 'custom_field_1',
        '101f59' => 'custom_field_2',
        '101f5a' => 'custom_field_3',
        '105dc0' => 'map_id',
        '105ddb' => 'link_from',
        '105ddc' => 'link_to',
//        '105ddb' => 'link_id',
        '105dc4' => 'hid_item_id',
        '105dc5' => 'lid_x',
        '105dc6' => 'lid_y',
        '105dc7' => 'hid_y',
        'fe0001' => 'record_id', //Also LinkId
        'fe0008' => 'active',
        'fe0009' => 'comment',
        'fe000a' => 'disabled',
        'fe000d' => 'default',
        'ffffff' => 'index_id',
    ];
    
    /**
     * @var PDO
     */
    protected $db;
    
    /**
     * @var string[]
     */
    private $devices;
    
    /**
     * @var string[]
     */
    private $types;
    
    /**
     * @var string[]
     */
    private $links;
    
    /**
     * Dude constructor.
     *
     * @param $path
     * @throws Exception
     */
    public function __construct($path)
    {
        if (!is_file($path)) {
            throw new Exception("Dude SQLite DB Path must be specified and valid");
        }
        $this->db = new PDO('sqlite:' . $path);
    }
    
    /**
     * @return string[]
     * @throws Exception
     */
    public function fetchDevices()
    {
        if ($this->devices) {
            return $this->devices;
        }
        $maps = $this->fetchMaps();
        $types = $this->fetchTypes();
        $links = $this->fetchLinks();
        $linksToMap = [];
        array_walk($links, function ($v) use (&$linksToMap) {
            $linksToMap[$v['hid_item_id']] = $v['map_id'];
        });
        
        $query = $this->db->query("SELECT * from objs WHERE HEX(obj) LIKE '4D320100FF8801000F%';");
        $devices = [];
        foreach ($query->fetchAll() as $row) {
            $data = $this->decode($row['obj']);
            if (isset($data['device_type_id'])) {
                $data['device_type'] = $types[$data['device_type_id']] ?? 'unknow';
            }
            if (isset($linksToMap[$row['id']])) {
                $data['map'] = $maps[$linksToMap[$row['id']]] ?? 'Default';
            }
            $devices[$row['id']] = $data;
        }
        $this->devices = $devices;
        return $devices;
    }
    
    /**
     * @return string[]
     * @throws Exception
     */
    public function fetchLinks()
    {
        if ($this->links) {
            return $this->links;
        }
        $query = $this->db->query("SELECT * from objs WHERE HEX(obj) LIKE '4D320100FF8801004A%';");
        $links = [];
        foreach ($query->fetchAll() as $row) {
            $data = $this->decode($row['obj']);
            $links[$row['id']] = $data;
        }
        $this->links = $links;
        return $links;
    }
    
    /**
     * @return string[]
     * @throws Exception
     */
    public function fetchMaps()
    {
        if ($this->devices) {
            return $this->devices;
        }
        $query = $this->db->query("SELECT * from objs WHERE HEX(obj) LIKE '4D320100FF8801000A%';");
        $maps = [];
        foreach ($query->fetchAll() as $row) {
            $data = $this->decode($row['obj']);
            $maps[$row['id']] = $data['name'];
        }
        $this->devices = $maps;
        return $maps;
    }
    
    /**
     * @return string[]
     * @throws Exception
     */
    public function fetchTypes()
    {
        if ($this->types) {
            return $this->types;
        }
        $query = $this->db->query("SELECT * from objs WHERE HEX(obj) LIKE '4D320100FF8801000E%';");
        $types = [];
        foreach ($query->fetchAll() as $row) {
            $data = $this->decode($row['obj']);
            $types[$row['id']] = $data['name'];
        }
        $this->types = $types;
        return $types;
    }
    
    /**
     * @param     $data
     * @param int $topID
     * @return array
     * @throws Exception
     */
    public function decode($data, $topID = 0)
    {
        $fp = fopen('data:text/plain;base64,'.base64_encode($data), 'rb');
        $allData = [];
        if ($topID === 0) {
            $signature = fread($fp, 2);
        }
        do {
            $r = fread($fp, 4);
            try {
                $bmarker = unpack("V", $r);
            } catch (\Throwable $e) {
                continue;
            }
            $bidraw = $bmarker[1] & 0xFFFFFF;
            $btype = $bmarker[1] >> 24;
            $data = null;
            switch ($btype) {
                case 0x21: //Short string
                    $blen = unpack("C", fread($fp, 1))[1];
                    if ($blen > 0) {
                        $data = fread($fp, $blen);
                    }
                    $mtype = "s";
                    break;
                case 0x31: //freelength list of bytes (mac address)
                    $blen = unpack("C", fread($fp, 1))[1];
                    $mtype = "r";
                    if ($blen > 0) {
                        if (dechex($bidraw) === '101f44') {
                            $data = [];
                            $macs = str_split(strtoupper(unpack("H*", fread($fp, $blen))[1]), 12);
                            foreach ($macs as $mac) {
                                $data[] = implode(":", str_split($mac, 2));
                            }
                        } else {
                            $data = fread($fp, $blen);
                        }
                    }
                    break;
                case 0x08: //int
                    $data = unpack("V*", fread($fp, 4))[1];
                    $mtype = "u";
                    break;
                case 0x10: //long
                    $data = unpack("P", fread($fp, 8))[1];
                    $mtype = "q";
                    break;
                case 0x18: //128bit integer
                    //Todo: We need to deal with 16 bytes integers...
                    $data = unpack("P*", fread($fp, 16));
                    $mtype = "a";
                    break;
                case 0x09: //byte
                    $data = unpack("C", fread($fp, 1))[1];
                    $mtype = 'u';
                    break;
                case 0x29: //single short M2 block
                    $sub_size = unpack("C", fread($fp, 1))[1];
                    $data = $this->decode(fread($fp,$sub_size),$topID << 24 + $bidraw);
                    $mtype = 'M';
                    break;
                case 0xA8: //array of M2 blocks
                    $arraysize = unpack("v", fread($fp, 2))[1];
                    $parser = 0;
                    $data = [];
                    while ($parser < $arraysize) {
                        $parser++;
                        $sub_size = unpack("v", fread($fp, 2))[1];
                        $data[] =  $this->decode(fread($fp,$sub_size),$topID << 24 + $bidraw);
                        fseek($fp, ftell($fp) - 2);
                    }
                    $mtype = "M";
                    break;
                case 0x88: #array of int
                    $arraysize = unpack("v", fread($fp, 2))[1];
                    $parser = 0;
                    $data = [];
                    while ($parser < $arraysize) {
                        $parser++;
                        $d = unpack("V*", fread($fp,4));
                        if (dechex($bidraw) === '101f40') {
                            $data[] = implode(".", array_reverse(explode(".", long2ip($d[1]))));
                        } else {
                            $data[] = $d[1] ?? null;
                        }
                    }
                    $mtype = "U";
                    break;
                case 0xA0: //array of strings
                    $arraysize = unpack("v", fread($fp, 2))[1];
                    $parser = 0;
                    $data = [];
                    while ($parser < $arraysize) {
                        $parser++;
                        $sub_size = unpack("v", fread($fp, 2))[1];
                        $data[] =  fread($fp, $sub_size);
                        fseek($fp, ftell($fp) - 2);
                    }
                    $mtype = "S";
                    break;
                case 0x00:
                case 0x01:
                    $data = (bool) $btype;
                    $mtype = 'b';
                    break;
                default:
                    $mtype = null;
            }
            $allData[self::$labels[dechex($bidraw)] ?? dechex($bidraw)] = $data;
        } while (!feof($fp));
        
        return $allData;
    }
}