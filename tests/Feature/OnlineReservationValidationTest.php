<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Http\Middleware\VerifyApiSignature;
// Jhon | 2026-11-18 15:36 WITA | TAMBAH: Tanpa signature ditolak; validasi JSON tidak pernah mencapai database untuk input kosong.
class OnlineReservationValidationTest extends TestCase {
 public function test_signature_is_required(){$this->getJson('/api/service/reservasi-online/master')->assertStatus(401);}
 public function test_missing_fields_return_json_422(){$this->withoutMiddleware(VerifyApiSignature::class)->postJson('/api/service/reservasi-online',[])->assertStatus(422)->assertJsonValidationErrors(['patient_id','nocm','request_id','poli_id','tanggal']);}
 public function test_slot_inputs_are_validated(){$this->withoutMiddleware(VerifyApiSignature::class)->getJson('/api/service/reservasi-online/slot?poli_id=bad&dokter_id=0&tanggal=bad')->assertStatus(422)->assertJsonValidationErrors(['poli_id','dokter_id','tanggal']);}
}
