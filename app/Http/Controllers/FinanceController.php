<?php

namespace App\Http\Controllers;

use App\Models\{Mahasiswa, Pembayaran, SemesterAkademik, Tagihan};
use App\Rules\MoneyAmount;
use App\Support\{Audit, Money};
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Tagihan::with('mahasiswa', 'term', 'pembayaran');
        if ($request->user()->role === 'mahasiswa') {
            $student = $request->user()->mahasiswa;
            abort_unless($student, 403);
            $query->where('mahasiswa_id', $student->id);
        }
        return view('finance.index', [
            'rows' => $query->latest()->paginate(20),
            'students' => in_array($request->user()->role, ['keuangan', 'admin']) ? Mahasiswa::orderBy('nama')->get() : collect(),
            'terms' => SemesterAkademik::latest('krs_mulai')->get(),
        ]);
    }

    private function verifyRepeatedBill(Tagihan $bill, array $data): void
    {
        foreach (['mahasiswa_id', 'semester_akademik_id', 'keterangan', 'jumlah', 'jatuh_tempo'] as $field) {
            if ((string) $bill->$field !== (string) $data[$field]) {
                throw ValidationException::withMessages(['request_key' => 'Formulir ini sudah menghasilkan tagihan dengan data berbeda. Muat ulang halaman untuk membuat tagihan baru.']);
            }
        }
    }

    public function bill(Request $request)
    {
        $data = $request->validate([
            'mahasiswa_id' => 'required|integer|exists:mahasiswa,id',
            'semester_akademik_id' => 'required|integer|exists:semester_akademik,id',
            'keterangan' => 'required|string|max:200', 'jumlah' => ['required', new MoneyAmount],
            'jatuh_tempo' => 'required|date_format:Y-m-d', 'request_key' => 'nullable|uuid',
        ]);
        $data['jumlah'] = Money::decimal(Money::cents($data['jumlah']));
        try {
            DB::transaction(function () use ($data) {
                $existing = empty($data['request_key']) ? null : Tagihan::where('request_key', $data['request_key'])->lockForUpdate()->first();
                if ($existing) {
                    $this->verifyRepeatedBill($existing, $data);
                    return;
                }
                $bill = Tagihan::create($data);
                Audit::record('bill.created', $bill);
            }, 3);
        } catch (QueryException $e) {
            // A simultaneous retry can hit the unique key before it sees the first commit.
            $existing = empty($data['request_key']) ? null : Tagihan::where('request_key', $data['request_key'])->first();
            if (! $existing || ! in_array((string) $e->getCode(), ['23000', '23505', '19'])) {
                throw $e;
            }
            $this->verifyRepeatedBill($existing, $data);
        }
        return back()->with('success', 'Tagihan tersimpan. Pengiriman ulang formulir yang sama tidak membuat tagihan ganda.');
    }

    public function pay(Request $request, Tagihan $tagihan)
    {
        $data = $request->validate([
            'jumlah' => ['required', new MoneyAmount], 'tanggal' => 'required|date_format:Y-m-d|before_or_equal:today',
            'referensi' => 'required|string|max:100|unique:pembayaran,referensi',
        ]);
        $cents = Money::cents($data['jumlah']);
        $data['jumlah'] = Money::decimal($cents);
        try {
            DB::transaction(function () use ($tagihan, $data, $cents, $request) {
                $bill = Tagihan::lockForUpdate()->findOrFail($tagihan->id);
                if ($cents > $bill->remainingCents()) {
                    throw ValidationException::withMessages(['jumlah' => 'Pembayaran melebihi sisa tagihan. Muat ulang halaman untuk melihat saldo terbaru.']);
                }
                $payment = $bill->pembayaran()->create($data + ['dicatat_oleh' => $request->user()->id]);
                Audit::record('payment.recorded', $payment, ['tagihan_id' => $bill->id, 'jumlah' => $data['jumlah']]);
            }, 3);
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505', '19']) && Pembayaran::where('referensi', $data['referensi'])->exists()) {
                throw ValidationException::withMessages(['referensi' => 'Nomor referensi sudah dicatat. Periksa riwayat sebelum mengulang pembayaran.']);
            }
            throw $e;
        }
        return back()->with('success', 'Pembayaran manual berhasil dicatat.');
    }

    public function receipt(Request $request, Pembayaran $pembayaran)
    {
        if ($request->user()->role === 'mahasiswa') {
            abort_unless($pembayaran->tagihan->mahasiswa->user_id === $request->user()->id, 403);
        }
        $pembayaran->load('tagihan.mahasiswa', 'tagihan.term');
        return view('finance.receipt', compact('pembayaran'));
    }
}
