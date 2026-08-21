<?php

namespace App\Imports;

use App\Models\Identity\Users\Teacher;
use App\Models\User;
use App\Rules\EcuadorianPhone;
use App\Rules\FlexibleDni;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TeachersImport implements ToCollection, WithHeadingRow
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
        $lastTeacher = Teacher::orderBy('id', 'desc')->first();
        $this->currentCodeNumber = $lastTeacher ? $lastTeacher->id : 0;
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((int) $value))->format('Y-m-d');
        }

        $formats = ['j/n/Y', 'j/n/y', 'd/m/Y', 'd/m/y', 'j-m-Y', 'j-n-Y', 'd-m-Y', 'd-m-y', 'Y-m-d', 'm/d/Y', 'm-d-Y'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function collection(Collection $rows): void
    {
        $this->totalRows = $rows->count();

        foreach ($rows as $row) {
            $rowData = [
                'name' => $row['nombres'] ?? $row['nombre'] ?? $row['name'] ?? null,
                'lastname' => $row['apellidos'] ?? $row['apellido'] ?? $row['lastname'] ?? null,
                'dni' => (string) ($row['dni'] ?? ''),
                'email' => $row['email'] ?? $row['correo'] ?? null,
                'phone' => (string) ($row['telefono'] ?? $row['phone'] ?? ''),
                'cellphone' => (string) ($row['celular'] ?? $row['cellphone'] ?? ''),
                'address' => $row['direccion'] ?? $row['address'] ?? null,
                'specialization' => $row['especializacion'] ?? $row['ESPECIALIZACION'] ?? $row['specialization'] ?? null,
                'title' => $row['titulo'] ?? $row['TITULO'] ?? $row['title'] ?? null,
                'education_level' => $row['nivel_educativo'] ?? $row['NIVEL_EDUCATIVO'] ?? $row['education_level'] ?? null,
                'hire_date' => $this->parseDate($row['fecha_ingreso'] ?? $row['FECHA_INGRESO'] ?? $row['hire_date'] ?? null),
            ];

            try {
                $existingUser = $rowData['dni'] ? User::where('dni', $rowData['dni'])->first() : null;

                $rules = [
                    'name' => ['required', 'string', 'max:255'],
                    'lastname' => ['required', 'string', 'max:255'],
                    'dni' => ['required', new FlexibleDni],
                    'email' => ['required', 'email'],
                    'cellphone' => ['required', new EcuadorianPhone],
                ];

                if (! empty($rowData['phone'])) {
                    $rules['phone'] = [new EcuadorianPhone];
                }

                if (! $existingUser) {
                    $rules['dni'][] = 'unique:users,dni';
                    $rules['email'][] = 'unique:users,email';
                }

                $validator = Validator::make($rowData, $rules, [
                    'name.required' => 'Nombre es obligatorio',
                    'lastname.required' => 'Apellido es obligatorio',
                    'dni.required' => 'DNI es obligatorio',
                    'dni.unique' => 'DNI ya registrado',
                    'email.required' => 'Email es obligatorio',
                    'email.email' => 'Formato de email invalido',
                    'email.unique' => 'Email ya registrado',
                    'cellphone.required' => 'Celular es obligatorio',
                ]);

                if ($validator->fails()) {
                    $rowData['errors'] = $validator->errors()->first();
                    $this->errorRows++;
                } else {
                    $this->validRows++;
                }
            } catch (\Exception $e) {
                $rowData['errors'] = $e->getMessage();
                $this->errorRows++;
            }

            $this->rows[] = $rowData;

            if (! $this->previewOnly && empty($rowData['errors'])) {
                $this->currentCodeNumber++;
                $teacherCode = 'DOC-'.str_pad($this->currentCodeNumber, 4, '0', STR_PAD_LEFT);

                if ($existingUser) {
                    $existingUser->update([
                        'name' => $rowData['name'],
                        'lastname' => $rowData['lastname'],
                        'email' => $rowData['email'],
                        'phone' => $rowData['phone'] ?: null,
                        'cellphone' => $rowData['cellphone'] ?: null,
                        'address' => $rowData['address'] ?: null,
                    ]);
                    $user = $existingUser;
                } else {
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

                    $user->assignRole('DOCENTE');
                }

                $existingTeacher = Teacher::where('user_id', $user->id)->first();

                if ($existingTeacher) {
                    $existingTeacher->update([
                        'specialization' => $rowData['specialization'] ?? null,
                        'title' => $rowData['title'] ?? null,
                        'education_level' => $rowData['education_level'] ?? null,
                        'hire_date' => $rowData['hire_date'] ?: null,
                    ]);
                } else {
                    Teacher::create([
                        'user_id' => $user->id,
                        'teacher_code' => $teacherCode,
                        'specialization' => $rowData['specialization'] ?? null,
                        'title' => $rowData['title'] ?? null,
                        'education_level' => $rowData['education_level'] ?? null,
                        'hire_date' => $rowData['hire_date'] ?: null,
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
