<?php
// Incluir la librería
require_once 'SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

// Ruta del archivo Excel
$archivo =  'datos.xlsx';

// Inicializamos variables
$filas = [];
$error = '';

// Verificamos si el archivo existe
if (!file_exists($archivo)) {
    $error = "El archivo datos.xlsx no se encuentra en la carpeta actual.";
} else {
    // Procesamos el archivo
    if ($xlsx = SimpleXLSX::parse($archivo)) {
        // Obtenemos las primeras 6 filas
        $filas_crudas = array_slice($xlsx->rows(), 0, 6);

        // Omitimos las dos primeras columnas de cada fila
        $filas = [];
        foreach ($filas_crudas as $fila) {
            $filas[] = array_slice($fila, 2); // Desde el índice 2 en adelante
        }
    } else {
        $error = SimpleXLSX::parseError();
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proyecciones de perdida del bosque de la provincia Padre Abad Region de ucayali</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <div class="container py-4">
        <h2 class="mb-4 text-center">Proyecciones de perdida del bosque de la provincia Padre Abad Region de ucayali</h2>
        <div class="row g-4">
            <!-- Card con tabla y gráfico -->
            <div class="col-lg-12">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-7">
                                <?php if ($error): ?>
                                    <p class="text-danger"><?php echo htmlspecialchars($error); ?></p>
                                <?php elseif (!empty($filas)): ?>
                                    <div class="table-responsive mb-4">
                                        <table class="table table-bordered table-sm align-middle">
                                            <thead>
                                                <tr>
                                                    <?php foreach ($filas[0] as $th): ?>
                                                        <th><?php echo htmlspecialchars($th); ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($filas, 1) as $fila): ?>
                                                    <tr>
                                                        <?php foreach ($fila as $td): ?>
                                                            <td><?php echo htmlspecialchars($td); ?></td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php
                                    // Obtener nombres de distritos (primera columna de cada fila, omitiendo encabezado)
                                    $distritos = [];
                                    foreach (array_slice($xlsx->rows(), 1, 5) as $fila) {
                                        $distritos[] = $fila[2]; // Tomamos la tercera columna (índice 2) como nombre del distrito
                                    }

                                    // Obtener los años de la primera fila (sin las dos primeras columnas)
                                    $anios = array_slice($filas[0], 1);

                                    // Calcular patrones para cada distrito (diferencia promedio anual)
                                    $proyecciones = [];
                                    $patrones = [];
                                    foreach (array_slice($filas, 1) as $fila) {
                                        $valores = array_map('floatval', array_slice($fila, 1));
                                        $deltas = [];
                                        for ($i = 1; $i < count($valores); $i++) {
                                            $deltas[] = $valores[$i] - $valores[$i - 1];
                                        }
                                        $promedio = count($deltas) ? array_sum($deltas) / count($deltas) : 0;
                                        $patrones[] = $promedio;

                                        // Proyectar hasta 2040
                                        $proy = $valores;
                                        $ultimo = end($proy);
                                        for ($anio = 2024; $anio <= 2040; $anio++) {
                                            $ultimo += $promedio;
                                            $proy[] = round($ultimo, 2);
                                        }
                                        $proyecciones[] = $proy;
                                    }

                                    // Construir encabezado de la tabla de proyección
                                    $anios_proy = $anios;
                                    for ($anio = 2024; $anio <= 2040; $anio++) {
                                        $anios_proy[] = $anio;
                                    }
                                    ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-5 d-flex align-items-center">
                                <canvas id="myChart" width="100%" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Card adicional 1 -->
            <div class="col-lg-12">
                <div class="card shadow h-100">
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Distrito</th>
                                        <?php foreach ($anios_proy as $anio): ?>
                                            <th><?php echo htmlspecialchars($anio); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proyecciones as $idx => $proy): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($distritos[$idx]); ?></td>
                                            <?php foreach ($proy as $valor): ?>
                                                <td><?php echo htmlspecialchars($valor); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="card shadow h-100 mt-4 mt-lg-0">
                    <div class="card-body d-flex flex-column">
                        <div class="mt-4">
                            <canvas id="proyeccionChart" width="100%" height="60"></canvas>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                <?php if (!empty($proyecciones) && !empty($distritos)): ?>
                                    const proyLabels = <?php echo json_encode($anios_proy); ?>;
                                    const proyData = <?php
                                        // Generar datasets para cada distrito
                                        $datasets = [];
                                        $colors = ['#007bff', '#dc3545', '#28a745', '#ffc107', '#6f42c1', '#20c997', '#fd7e14'];
                                        foreach ($proyecciones as $idx => $proy) {
                                            $datasets[] = [
                                                'label' => $distritos[$idx],
                                                'data' => array_map('floatval', $proy),
                                                'borderColor' => $colors[$idx % count($colors)],
                                                'backgroundColor' => $colors[$idx % count($colors)],
                                                'fill' => false,
                                                'tension' => 0.2
                                            ];
                                        }
                                        echo json_encode($datasets);
                                    ?>;
                                    const ctxProy = document.getElementById('proyeccionChart').getContext('2d');
                                    new Chart(ctxProy, {
                                        type: 'line',
                                        data: {
                                            labels: proyLabels,
                                            datasets: proyData
                                        },
                                        options: {
                                            responsive: true,
                                            plugins: {
                                                legend: { display: true }
                                            },
                                            scales: {
                                                y: { beginAtZero: true }
                                            }
                                        }
                                    });
                                <?php endif; ?>
                            });
                            </script>
                    </div>
                </div>
            </div>
            <!-- Card adicional 2 -->
            <div class="col-lg-12">
                <div class="card shadow h-100 mt-4 mt-lg-0">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title mb-3">Metodología y Conclusiones</h5>
                        <p>
                            <strong>Metodología:</strong><br>
                            El patrón matemático utilizado para la proyección es el promedio anual de cambio de deforestación para cada distrito entre 2001 y 2023.
                            Este promedio se suma cada año a partir del último dato disponible (2023) para estimar los valores hasta 2040.
                            Los distritos considerados son: <strong><?php echo implode(', ', array_map('htmlspecialchars', $distritos)); ?></strong>.
                        </p>
                        <hr>
                        <p>
                            <strong>Patrones encontrados:</strong><br>
                            <?php foreach ($distritos as $idx => $distrito): ?>
                                <span class="d-block mb-1">
                                    <strong><?php echo htmlspecialchars($distrito); ?>:</strong>
                                    <?php echo number_format($patrones[$idx], 2); ?> ha/año
                                </span>
                            <?php endforeach; ?>
                        </p>
                        <hr>
                        <p>
                            <strong>Fórmula matemática empleada:</strong><br>
                            <code>
                                Proyección<sub>n+1</sub> = Proyección<sub>n</sub> + Patrón<br>
                                donde Patrón = promedio anual de (valor<sub>i+1</sub> - valor<sub>i</sub>) entre 2001 y 2023
                            </code>
                        </p>
                        <hr>
                        <p>
                            <strong>Conclusiones:</strong><br>
                            Las proyecciones muestran una tendencia continua de pérdida de bosque en los distritos analizados si se mantienen los patrones históricos. 
                            Es fundamental implementar estrategias de conservación y monitoreo para revertir esta tendencia y proteger los recursos forestales de la región.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Chart.js script -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Gráfico de líneas con 5 distritos (filas), cada uno una línea
        <?php if (!empty($filas) && count($filas) > 1): ?>
            const labels = <?php echo json_encode(array_slice($filas[0], 1)); ?>;
            const datasets = [];
            const colors = ['#007bff', '#dc3545', '#28a745', '#ffc107', '#6f42c1'];
            <?php for ($i = 1; $i <= 5; $i++): ?>
                datasets.push({
                    label: <?php echo json_encode($filas[$i][0]); ?>,
                    data: <?php echo json_encode(array_map('floatval', array_slice($filas[$i], 1))); ?>,
                    borderColor: colors[<?php echo $i-1; ?>],
                    backgroundColor: colors[<?php echo $i-1; ?>] + '33',
                    fill: false,
                    tension: 0.2
                });
            <?php endfor; ?>
            const ctx = document.getElementById('myChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        <?php endif; ?>
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
