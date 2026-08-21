<?php

namespace App\Imports;

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\Student;
use App\Models\User;
use App\Rules\EcuadorianPhone;
use App\Rules\FlexibleDni;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RepresentativesImport implements ToCollection, WithHeadingRow
{
    protected bool $previewOnly;

    protected array $rows = [];

    protected int $totalRows = 0;

    protected int $validRows = 0;

    protected int $errorRows = 0;

    protected int $currentCodeNumber = 0;

    public function __construct(bool $previewOnly = false)
    {
        $this->previewOnly = $previewOnly;
        $lastRepresentative = Representative::orderBy('id', 'desc')->first();
        $this->currentCodeNumber = $lastRepresentative ? $lastRepresentative->id : 0;
    }

    public function collection(Collection $rows): void
    {
        $this->totalRows = $rows->count();

        foreach ($rows as $row) {
            $studentDni = (string) ($row['dni_estudiante'] ?? $row['DNI_ESTUDIANTE'] ?? $row['student_dni'] ?? '');
            $student = null;
            if ($studentDni !== '') {
                $studentUser = User::where('dni', $studentDni)->first();
                $student = $studentUser ? Student::where('user_id', $studentUser->id)->first() : null;
            }

            $rowData = [
                'name' => $row['nombres'] ?? $row['nombre'] ?? $row['name'] ?? null,
                'lastname' => $row['apellidos'] ?? $row['apellido'] ?? $row['lastname'] ?? null,
                'dni' => (string) ($row['dni'] ?? ''),
                'email' => $row['email'] ?? $row['correo'] ?? null,
                'phone' => (string) ($row['telefono'] ?? $row['phone'] ?? ''),
                'cellphone' => (string) ($row['celular'] ?? $row['cellphone'] ?? ''),
                'address' => $row['direccion'] ?? $row['address'] ?? null,
                'student_dni' => $studentDni,
                'relationship' => $row['parentesco'] ?? $row['PARENTESCO'] ?? $row['relationship'] ?? null,
                'occupation' => $row['ocupacion'] ?? $row['OCUPACION'] ?? $row['occupation'] ?? null,
                'work_phone' => (string) ($row['telefono_laboral'] ?? $row['TELEFONO_LABORAL'] ?? $row['work_phone'] ?? ''),
            ];

            try {
                $existingUser = $rowData['dni'] ? User::where('dni', $rowData['dni'])->first() : null;

                $rules = [
                    'name' => ['required', 'string', 'max:255'],
                    'lastname' => ['required', 'string', 'max:255'],
                    'dni' => ['required', new FlexibleDni],
                    'email' => ['nullable', 'email'],
                    'student_dni' => ['required', new FlexibleDni],
                    'cellphone' => ['required', new EcuadorianPhone],
                ];

                if (! empty($rowData['phone'])) {
                    $rules['phone'] = [new EcuadorianPhone];
                }

                if (! empty($rowData['work_phone'])) {
                    $rules['work_phone'] = [new EcuadorianPhone];
                }

                if (! $existingUser) {
                    $rules['dni'][] = 'unique:users,dni';
                    $rules['email'][] = 'unique:users,email';
                }

                $validator = Validator::make($rowData, $rules, [
                    'name.required' => 'Nombre es obligatorio',
                    'lastname.required' => 'Apellido es obligatorio',
                    'dni.required' => 'DNI es obligatorio',
                    // 'email.required' => 'Email es obligatorio',
                    'email.email' => 'Formato de email invalido',
                    'student_dni.required' => 'DNI del estudiante es obligatorio',
                    'cellphone.required' => 'Celular es obligatorio',
                ]);

                if ($validator->fails()) {
                    $rowData['errors'] = $validator->errors()->first();
                    $this->errorRows++;
                } elseif (! $student) {
                    $rowData['errors'] = 'Estudiante no encontrado con DNI: '.$studentDni;
                    $this->errorRows++;
                } else {
                    $rowData['status'] = $existingUser ? 'actualizar' : 'nuevo';
                    $this->validRows++;
                }
            } catch (\Exception $e) {
                $rowData['errors'] = $e->getMessage();
                $this->errorRows++;
            }

            $this->rows[] = $rowData;

            if (! $this->previewOnly && empty($rowData['errors']) && $student) {
                $this->currentCodeNumber++;
                $representstiveCode = 'REP-'.str_pad($this->currentCodeNumber, 4, '0', STR_PAD_LEFT);
                if ($existingUser) {
                    $existingUser->update([
                        'name' => $rowData['name'],
                        'lastname' => $rowData['lastname'],
                        // 'email' => $rowData['email'],
                        'phone' => $rowData['phone'] ?: null,
                        'cellphone' => $rowData['cellphone'] ?: null,
                        'address' => $rowData['address'] ?: null,
                    ]);
                    $user = $existingUser;
                } else {
                    $provisionalEmail = $rowData['email'];
                    if (empty($provisionalEmail)) {
                        $firstName = strtolower(trim(explode(' ', $rowData['name'])[0]));
                        $lastName = strtolower(trim(explode(' ', $rowData['lastname'])[0]));
                        $provisionalEmail = $firstName.'.'.$lastName.'.'.mb_strtolower($representstiveCode).'@educaplusrepresentante.edu.ec';
                    }
                    $user = User::create([
                        'name' => $rowData['name'],
                        'lastname' => $rowData['lastname'],
                        'dni' => $rowData['dni'],
                        'email' => $rowData['email'],
                        'phone' => $rowData['phone'] ?: null,
                        'cellphone' => $rowData['cellphone'] ?: null,
                        'address' => $rowData['address'] ?: null,
                        'password' => $rowData['dni'].'passsword',
                        'status' => 1,
                        'must_change_password' => true,
                    ]);

                    $user->assignRole('REPRESENTANTE');
                }

                $existingRep = Representative::where('user_id', $user->id)
                    ->where('student_id', $student->id)
                    ->first();

                if (! $existingRep) {
                    Representative::create([
                        'user_id' => $user->id,
                        'student_id' => $student->id,
                        'relationship' => $rowData['relationship'] ?? null,
                        'occupation' => $rowData['occupation'] ?? null,
                        'work_phone' => $rowData['work_phone'] ?? null,
                    ]);
                } else {
                    $existingRep->update([
                        'relationship' => $rowData['relationship'] ?? $existingRep->relationship,
                        'occupation' => $rowData['occupation'] ?? $existingRep->occupation,
                        'work_phone' => $rowData['work_phone'] ?? $existingRep->work_phone,
                    ]);
                }
            }
        }
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getValidRows(): int
    {
        return $this->validRows;
    }

    public function getErrorRows(): int
    {
        return $this->errorRows;
    }
}
