<?php
session_start();
if (!isset($_SESSION['user_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'university_hr';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

define('INCLUDE_PERMISSION', true);

// Get available years from promotion history
$yearsStmt = $pdo->query("SELECT DISTINCT promotion_date as year FROM promotion_history ORDER BY year DESC");
$availableYears = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (count($availableYears) > 0 ? max($availableYears) : date('Y'));

$requiredDurations = [
    'أستاذ' => 2.5,
    'محاضر قسم أ' => 2.5,
    'محاضر قسم ب' => 2.5,
    'مساعد قسم أ' => 2.5,
    'مساعد قسم ب' => 2.5,
];

function getProfessorsByRank($pdo, $rank) {
    $stmt = $pdo->prepare("SELECT * FROM professors WHERE academic_rank = ? ORDER BY last_promotion_date");
    $stmt->execute([$rank]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPromotionHistory($pdo, $year) {
    $stmt = $pdo->prepare("SELECT ph.*,p.hire_date, p.academic_rank, p.first_name, p.grade, last_promotion_date, p.last_name, p.matricule, p.grade as current_grade 
                          FROM promotion_history ph
                          JOIN professors p ON ph.professor_id = p.id
                          WHERE ph.promotion_date = ?
                          ORDER BY ph.previous_grade");
    $stmt->execute([$year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$promotionHistory = getPromotionHistory($pdo, $currentYear);

function calculateDurations($primaryDate, $fallbackDate, $year, $requiredDuration, $grade, $grade_p) {
    $start = new DateTime($primaryDate ?: $fallbackDate);
    $end = new DateTime($year . '-12-31');
    $diff = $end->diff($start);
    
    // Base calculation
    $base = $diff->y . 'y ' . $diff->m . 'm ' . $diff->d . 'd';
    
    // Bonus calculation
    $bonusMonths = 0;
    $bonusDays = 0;
    
    if ($grade == 0 && $diff->y >= 3) {
        // For grade 0 with ≥3 years: 2 months per year + 5 days per month (including partial months)
        $bonusMonths = $diff->y * 2;
        $bonusDays = $diff->m * 5 + ($diff->d > 0 ? 5 : 0); // Approximate days calculation
        
        // Convert extra days to months if needed
        if ($bonusDays >= 30) {
            $bonusMonths += floor($bonusDays / 30);
            $bonusDays = $bonusDays % 30;
        }
    } elseif ($grade > 0) {
        // For grades > 0: standard 2 months per year
        $bonusMonths = $diff->y * 2;
    }
    
    // Calculate years and remaining months from bonus
    $bonusYears = floor($bonusMonths / 12);
    $remainingBonusMonths = $bonusMonths % 12;
    
    $bonus = $bonusYears . 'y ' . $remainingBonusMonths . 'm ' . $bonusDays . 'd';
    
    // Combined calculation
    $totalYears = $diff->y + $bonusYears;
    $totalMonths = $diff->m + $remainingBonusMonths;
    $totalDays = $diff->d + $bonusDays;
    
    // Handle overflow from days
    if ($totalDays >= 30) {
        $totalMonths += floor($totalDays / 30);
        $totalDays = $totalDays % 30;
    }
    
    // Handle overflow from months
    if ($totalMonths >= 12) {
        $totalYears += floor($totalMonths / 12);
        $totalMonths = $totalMonths % 12;
    }
    
    $combined = $totalYears . 'y ' . $totalMonths . 'm ' . $totalDays . 'd';
    // Convert required duration (e.g., 2.5, 3, 3.5) to months
    $requiredMonths = (int)($requiredDuration * 12); // e.g., 2.5 years → 30 months
    $totalMonthsAll = ($totalYears * 12) + $totalMonths; // Total duration in months

    // Calculate remaining after subtraction
    if ($totalMonthsAll >= $requiredMonths) {
        $remainingMonths = $totalMonthsAll;
        do {
            $remainingMonths -= $requiredMonths;
            $grade++;
            $grade_p += 84 * $grade;
        }while($remainingMonths >= $requiredMonths);
        $remainingYears = floor($remainingMonths / 12);
        $remainingMonths = $remainingMonths % 12;
        $remaining = $remainingYears . 'y ' . $remainingMonths . 'm ' . $totalDays . 'd';
        
        // Calculate eligibility date (Dec 31 of current year - remaining duration)
        $eligibilityDate = clone $end; // Start from Dec 31 of current year
        $eligibilityDate->sub(new DateInterval("P{$remainingYears}Y{$remainingMonths}M{$totalDays}D"));
    } else {
        $remaining = '- - -';
        $eligibilityDate = null; // Not eligible
    }

    return [
        'base' => $base,
        'bonus' => $bonus,
        'combined' => $combined,
        'remaining' => $remaining,
        'eligibility_date' => $eligibilityDate ? $eligibilityDate->format('Y-m-d') : 'Not eligible',
        'grade' => $grade,
        'grade_p' => $grade_p,
    ];
}

$categories = [
    'الأساتذة' => getProfessorsByRank($pdo, 'أستاذ'),
    'المحاضرون' => getProfessorsByRank($pdo, 'محاضر قسم أ'),
    'المساعدون' => getProfessorsByRank($pdo, 'مساعد قسم أ'),
    'محاضر قسم أ' => getProfessorsByRank($pdo, 'محاضر قسم أ'),
    'محاضر قسم ب' => getProfessorsByRank($pdo, 'محاضر قسم ب'),
    'مساعد قسم أ' => getProfessorsByRank($pdo, 'مساعد قسم أ'),
    'مساعد قسم ب' => getProfessorsByRank($pdo, 'مساعد قسم ب')
];

$lecturersCategory = [
    'محاضر قسم أ' => getProfessorsByRank($pdo, 'محاضر قسم أ'),
    'محاضر قسم ب' => getProfessorsByRank($pdo, 'محاضر قسم ب')
];

$assistantsCategory = [
    'مساعد قسم أ' => getProfessorsByRank($pdo, 'مساعد قسم أ'),
    'مساعد قسم ب' => getProfessorsByRank($pdo, 'مساعد قسم ب')
];

function calculateDateOfEffectiveness($old_grade, $new_grade, $promotion_effective_date) {
    $diff = $new_grade - $old_grade;
    $date = explode("-", $promotion_effective_date);
    if($diff > 0) {
        $month = $date[1] + $diff * 6;
        $year = $date[0] + $diff * 2;
        $year += floor($month / 12);
        $month = $month % 12;
        $day = $date[2];
        return $year . '/' . $month . '/' . $day ; 
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجل الترقيات - نظام إدارة الموارد البشرية</title>
    <link rel="stylesheet" href="css/icons.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/promotions.css">
</head>
<body>
    <div class="container">
        <?php include 'header.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-history"></i> سجل الترقيات</h1>
                <div class="header-actions">
                    <form method="GET" class="year-selector-form">
                        <select name="year" onchange="this.form.submit()">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= $year ?>" <?= $year == $currentYear ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span> :اختر السنة </span>
                    </form>
                </div>
                <a href="promotions_tmp.php">ترقيات عام <?= date('Y')?></a>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <div>
                        <i class="fas fa-check-circle"></i>
                        <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                    </div>
                    <button class="close-btn" onclick="this.parentElement.style.display='none'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <div>
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                    <button class="close-btn" onclick="this.parentElement.style.display='none'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <div class="promotion-tabs">
                <?php foreach (array_slice($categories,0,3) as $categoryName => $professors): ?>
                    <button class="tab-btn" onclick="openTab('<?= str_replace(' ', '-', $categoryName) ?>')">
                        <?= $categoryName ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($categories as $categoryName => $professors): ?>
                <div id="<?= str_replace(' ', '-', $categoryName) ?>" class="tab-content" style="display: <?= $categoryName === 'الأساتذة' ? 'block' : 'none' ?>;">
                <?php if($categoryName == 'المحاضرون' || $categoryName == 'محاضر قسم أ'  || $categoryName == 'محاضر قسم ب'): ?>
                        <div class="promotion-tabs" id="lecturersTabs">
                            <?php foreach ($lecturersCategory as $lecturerCategoryName => $lecturerProfessors): ?>
                                <button class="tab-btn" onclick="openTab('<?= str_replace(' ', '-', $lecturerCategoryName) ?>')">
                                    <?= $lecturerCategoryName ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($categoryName == 'المساعدون' || $categoryName == 'مساعد قسم أ'  || $categoryName == 'مساعد قسم ب'): ?>
                        <div class="promotion-tabs" id="assistantsTabs">
                            <?php foreach ($assistantsCategory as $assistantCategoryName => $assistantProfessors): ?>
                                <button class="tab-btn" onclick="openTab('<?= str_replace(' ', '-', $assistantCategoryName) ?>')">
                                    <?= $assistantCategoryName ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>    
                
                <div class="promotion-stats">
                        <p>عدد الترقيات في <?= $currentYear ?>: <?= count($promotionHistory) ?></p>
                    </div>

                    <table class="promotion-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>الدرجة</th>
                                <th>الرقم الإستدلالي</th>
                                <th>تاريخ السريان</th>
                                <th>الأقدمية الى غاية  <?= $currentYear?>/12/31</th>
                                <th>الإنقطاع ومنفعة أقدمية المنطقة الى <?= $currentYear?>/12/31</th>
                                <th>الأقدمية الكلية <?= $currentYear?>/12/31</th>
                                <th>النقطة المحصل عليها</th>
                                <th>المدة</th>
                                <th>الدرجة</th>
                                <th>الرقم الإستدلالي</th>
                                <th>تاريخ الفعالية</th>
                                <th>الأقدمية المتبقية <?= $currentYear?>/12/31</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($promotionHistory)): ?>
                                <tr>
                                    <td colspan="16" class="text-center">لا توجد ترقيات مسجلة في هذه السنة</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($promotionHistory as $index => $promotion): 
                                    if (strpos($categoryName, 'الأساتذة') !== false && $promotion['academic_rank'] !== 'أستاذ') continue;
                                    if (strpos($categoryName, 'المحاضرون') !== false && $promotion['academic_rank'] !== 'محاضر قسم أ') continue;
                                    if (strpos($categoryName, 'المساعدون') !== false && $promotion['academic_rank'] !== 'مساعد قسم أ') continue;
                                    if (strpos($categoryName, 'محاضر قسم أ') !== false && $promotion['academic_rank'] !== 'محاضر قسم أ') continue;
                                    if (strpos($categoryName, 'محاضر قسم ب') !== false && $promotion['academic_rank'] !== 'محاضر قسم ب') continue;
                                    if (strpos($categoryName, 'مساعد قسم أ') !== false && $promotion['academic_rank'] !== 'مساعد قسم أ') continue;
                                    if (strpos($categoryName, 'مساعد قسم ب') !== false && $promotion['academic_rank'] !== 'مساعد قسم ب') continue;
                                ?>
                                    <tr>
                                    <?php $durations = calculateDurations($promotion['effective_date'], $promotion['hire_date'], $currentYear, $requiredDurations[$promotion['academic_rank']], $promotion['previous_grade'], 1680);?>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($promotion['first_name'] . ' ' . $promotion['last_name']) ?></td>
                                        <td><?= $promotion['previous_grade'] ?></td>
                                        <td><?= 1680 + $promotion['previous_grade'] * 84 ?></td>
                                        <td><?= date('d/m/Y', strtotime($promotion['effective_date'])) ?></td>
                                        <td><?= $durations['base']?></td>
                                        <td><?= $durations['bonus']?></td>
                                        <td><?= $promotion['total_seniority']?></td>
                                        <td class="mark"><?= $promotion['mark'] ? $promotion['mark'] : "-" ?></td>
                                        <td><?= $promotion['duration']?></td>
                                        <td><?= $promotion['new_grade'] ?></td>
                                        <td><?= 1680 + $promotion['new_grade'] * 84 ?></td>
                                        <td><?= $promotion['activity_date'] ?></td>
                                        <td><?= $promotion['remaining_seniority']?></td>
                                        <td><?= htmlspecialchars($promotion['note'])?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </main>
    </div>
    <script>
        function openTab(tabName) {
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].style.display = 'none';
            }
            
            const tabButtons = document.getElementsByClassName('tab-btn');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            document.getElementById(tabName).style.display = 'block';
            event.currentTarget.classList.add('active');
        }

        // Activate the first tab by default
        document.addEventListener('DOMContentLoaded', function() {
            const firstTab = document.querySelector('.tab-btn');
            if (firstTab) {
                firstTab.click();
            }
        });
               // Function to handle sorting of professors by grade, mark, effective date, hire date, and birthdate
function sortProfessors() {
    const tables = document.querySelectorAll('.promotion-table');
    
    tables.forEach(table => {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // Sort rows
        rows.sort((a, b) => {
            // 1. Sort by grade (descending) - column index 2
            const gradeA = parseInt(a.cells[2].textContent) || 0;
            const gradeB = parseInt(b.cells[2].textContent) || 0;
            
            if (gradeB !== gradeA) {
                return gradeA - gradeB;
            }
            
            // 2. Sort by mark/score (descending) - column index 8
            const markInputA = parseFloat(a.cells[8].textContent) || 0;
            const markInputB = parseFloat(b.cells[8].textContent) || 0;
            
            if (markInputB !== markInputA) {
                return markInputB - markInputA;
            }
            
            // 3. Sort by effective date (ascending) - column index 4
            const effectiveDateA = new Date(a.cells[4].textContent);
            const effectiveDateB = new Date(b.cells[4].textContent);
            
            if (effectiveDateA.getTime() !== effectiveDateB.getTime()) {
                return effectiveDateA.getTime() - effectiveDateB.getTime();
            }
            
            // 4. Sort by hire date (ascending) - using data attribute
            const hireDateA = new Date(a.dataset.hireDate || 0);
            const hireDateB = new Date(b.dataset.hireDate || 0);
            
            if (hireDateA.getTime() !== hireDateB.getTime()) {
                return hireDateA.getTime() - hireDateB.getTime();
            }
            
            // 5. Sort by birthdate (ascending) - using data attribute
            const birthDateA = new Date(a.dataset.birthDate || 0);
            const birthDateB = new Date(b.dataset.birthDate || 0);
            
            return birthDateA.getTime() - birthDateB.getTime();
        });
        
        // Update row indexes and reattach to tbody
        rows.forEach((row, index) => {
            row.cells[0].textContent = index + 1;
            tbody.appendChild(row);
        });
        
    });
}

// Function to update duration categories based on relative position in sorted list
function updateDurationCategories(tbody, rows) {
    // Only apply to rows with mark inputs (مساعد قسم ب, مساعد قسم أ, محاضر قسم ب)
    const rankedRows = rows.filter(row => row.querySelector('.mark'));
    if (rankedRows.length === 0) return;
    
    const totalRows = rankedRows.length;
    
    // Calculate thresholds for the categories
    const firstThreshold = Math.floor(totalRows * 0.4);    // 40%
    const secondThreshold = Math.floor(totalRows * 0.8);   // 40% + 40% = 80%
    
    // Assign duration categories
    rankedRows.forEach((row, index) => {
        const durationCell = row.cells[9]; // Column for duration (المدة)
        if (index < firstThreshold) {
            durationCell.textContent = 'دنيا';
            durationCell.dataset.duration = 'دنيا';
        } else if (index < secondThreshold) {
            durationCell.textContent = 'متوسطة';
            durationCell.dataset.duration = 'متوسطة';
        } else {
            durationCell.textContent = 'قصوى';
            durationCell.dataset.duration = 'قصوى';
        }
        
        // Update the hidden input field in the promotion form
        const durInput = row.querySelector('input[name="dur"]');
        if (durInput) {
            durInput.value = durationCell.textContent;
        }
    });
}

// Auto-sort when marks change
document.addEventListener('DOMContentLoaded', function() {
    const markInputs = document.querySelectorAll('.mark');
    markInputs.forEach(input => {
        input.addEventListener('input', sortProfessors);
    });
    
    // Initial sort
    sortProfessors();
    
    // Sort after tab change
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            setTimeout(sortProfessors, 100);
        });
    });
});
    </script>
</body>
</html>