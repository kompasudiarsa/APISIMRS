<?php
namespace App\Exceptions;
// Jhon | 2026-11-26 13:00 WITA | TAMBAH: konteks konflik mapping ruangan, tidak memuat pasien/query/kredensial.
class RlMappingConflictException extends \RuntimeException
{
    public $room;
    public function __construct(int $id, string $name, array $labels)
    {
        parent::__construct('Mapping layanan khusus ambigu.');
        $this->room = ['id'=>$id, 'name'=>mb_substr($name,0,120), 'services'=>array_slice($labels,0,20)];
    }
}
