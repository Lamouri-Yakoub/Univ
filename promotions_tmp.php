<?php
session_start();
if (!isset($_SESSION['user_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Connexion à la base de données
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



$requiredDurations = [
    'أستاذ' =>['دنيا' => 2.5,],
    'محاضر قسم أ' => ['دنيا' => 2.5, 'متوسطة' => 3, 'قصوى' => 3.5],
    'محاضر قسم ب' => ['دنيا' => 2.5, 'متوسطة' => 3, 'قصوى' => 3.5],
    'مساعد قسم أ' => ['دنيا' => 2.5, 'متوسطة' => 3, 'قصوى' => 3.5],
    'مساعد قسم ب' => ['دنيا' => 2.5, 'متوسطة' => 3, 'قصوى' => 3.5],
];

// Categories that require mark input and duration assignment
$categoriesWithMark = ['مساعد قسم ب', 'مساعد قسم أ', 'محاضر قسم ب'];

function getProfessorsByRank($pdo, $rank) {
    // Modified query to include birthdate and select all needed fields for sorting
    $stmt = $pdo->prepare("SELECT * FROM professors WHERE academic_rank = ? ORDER BY grade DESC");
    $stmt->execute([$rank]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateDurations($primaryDate, $fallbackDate, $requiredDuration, $grade, $grade_p) {
    $start = new DateTime($primaryDate ?: $fallbackDate);
    $end = new DateTime(date('Y') . '-12-31');
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

// Get professors by category
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

// Define the function to calculate adjustment based on sector origin
function calculateSectorAdjustment($yearsWorked, $isOutsideSector) {
    if ($isOutsideSector) {
        // If outside sector, add half the time
        return $yearsWorked / 2;
    } else {
        // If inside sector, add all the time
        return $yearsWorked;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ترقيات الأساتذة - نظام إدارة الموارد البشرية</title>
    <link rel="stylesheet" href="css/icons.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/promotions.css">
</head>
<body>
    <div class="container">
        <?php include 'header.php'; ?>

        <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-award"></i> ترقيات الأساتذة</h1>
            <div class="header-actions">
                
                <a href="promotions.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> رجوع
                </a>
            </div>
        </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <div class="promotion-tabs">
                <?php foreach (array_slice($categories, 0, 3) as $categoryName => $professors): ?>
                    <button class="tab-btn" onclick="openTab('<?= str_replace(' ', '-', $categoryName) ?>')">
                        <?= $categoryName ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                
                
                
                <?php foreach ($categories as $categoryName => $professors): ?>
                    <div id="<?= str_replace(' ', '-', $categoryName) ?>" class="tab-content">
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
                <table class="promotion-table">
                    <div class="promotion-stats">
                        <p>عدد الأساتذة: <?= count($professors) ?></p>
                    </div>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>الدرجة الحالية</th>
                                <th>الرقم الإستدلالي</th>
                                <th>تاريخ السريان</th>
                                <th>الأقدمية الى غاية  <?= date('Y') ?>/12/31</th>
                                <th>الإنقطاع ومنفعة أقدمية المنطقة الى <?= date('Y') ?>/12/31</th>
                                <th>الأقدمية الكلية <?= date('Y') ?>/12/31</th>
                                <th>النقطة المحصل عليها</th>
                                <th>المدة</th>
                                <th>الدرجة</th>
                                <th>الرقم الإستدلالي</th>
                                <th>تاريخ الفاعلية</th>
                                <th>الأقدمية المتبقية <?= date('Y') ?>/12/31</th>
                                <th>ملاحظات</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($professors as $index => $professor): 
                                $effectiveDate = $professor['last_promotion_date'] ? date('Y-m-d', strtotime($professor['last_promotion_date'])) : date('Y-m-d', strtotime($professor['hire_date']));
                                
                                // Check if this rank requires mark input
                                $requiresMarkInput = in_array($professor['academic_rank'], $categoriesWithMark);
                                
                                // For 'أستاذ' and 'محاضر قسم أ', set a fixed duration value
                                $initialDuration = 'دنيا';
                                if ($professor['academic_rank'] == 'أستاذ' || $professor['academic_rank'] == 'محاضر قسم أ') {
                                    $initialDuration = 'دنيا';
                                }
                                $durations = calculateDurations($professor['last_promotion_date'], $professor['hire_date'], $requiredDurations[$professor['academic_rank']][$initialDuration], $professor['grade'], 1680);
                                $canPromote = $durations['grade'] - $professor['grade'] > 0;
                                // For categories with mark input, the duration will be set by JavaScript
                            ?>
                                <tr data-hire-date="<?= htmlspecialchars($professor['hire_date']) ?>" data-birth-date="<?= htmlspecialchars($professor['birth_date'] ?? '') ?>" data-rank="<?= htmlspecialchars($professor['academic_rank']) ?>">
                                    <input type="hidden" name="professor_id" value="<?= $professor['id'] ?>">
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($professor['first_name'] . ' ' . $professor['last_name']) ?></td>
                                    <td><?= htmlspecialchars($professor['grade'] ?? 0) ?></td>
                                    <td><?= 1680 + $professor['grade'] * 84?></td>
                                    <td><?= $effectiveDate ?></td>
                                    <td><?= $durations['base']?></td>
                                    <td><?= $durations['bonus']?></td>
                                    <td><?= $durations['combined']?></td>
                                    <td>
                                        <?php if ($requiresMarkInput): ?>
                                            <input type="number" class="mark" min="0" max="100" step="0.25">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $initialDuration ?></td>
                                    <td><?= $durations['remaining'] != '- - -'? $durations['grade'] : '-'?></td>
                                    <td><?= $durations['remaining'] != '- - -'? $durations['grade_p'] : '-'?></td>
                                    <td><?= $durations['eligibility_date']?></td>
                                    <td><?= $durations['remaining'] != '- - -'? $durations['remaining'] : "_____"?></td>
                                    <td></td>
                                    
                                    <td class="actions">
                                        <?php if ($canPromote): ?>
                                            <form method="post" action="process_promotion.php" class="promotion-form" enctype="multipart/form-data">
                                                <input type="hidden" name="professor_id" value="<?= $professor['id'] ?>">
                                                <input type="hidden" name="previous_grade" value="<?= $professor['grade'] ?>">
                                                <input type="hidden" name="new_grade" value="<?= $durations['grade'] ?>">
                                                <input type="hidden" name="effective_date" value="<?= $effectiveDate?>">
                                                <input type="hidden" name="eligibility_date" value="<?= $durations['eligibility_date'] ?>">
                                                <input type="hidden" name="dur" value="<?= $initialDuration ?>">
                                                <input type="hidden" name="mark" value="0">
                                                <input type="hidden" name="total_seniority" value="<?= $durations['combined']?>">
                                                <input type="hidden" name="activity_date" value="<?= $durations['eligibility_date']?>">
                                                <input type="hidden" name="remaining_seniority" value="<?= $durations['remaining']?>">
                                                <input type="hidden" name="note" value="">
<!--                                                 
                                                <div class="form-section">
                                                    <h2 class="section-title"><i class="fas fa-file-upload"></i> رفع ملف</h2>
                                                    <div class="form-grid">
                                                        <div class="form-group">
                                                            <label for="professor_file">ملف الأستاذ</label>
                                                            <input type="file" id="professor_file" name="professor_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple>
                                                            <small class="form-hint">يمكنك رفع ملفات PDF, Word, أو صور (الحد الأقصى: 5MB)</small>
                                                        </div>
                                                    </div>
                                                </div> -->

                                                <button type="submit" class="btn btn-promote" title="ترقية">
                                                    <i class="fas fa-arrow-up"></i> ترقية (<?= $durations['grade'] - $professor['grade'] ?> درجة)
                                                </button>
                                            </form>
                                            <?php else: ?>
                                                <input type="hidden" name="professor_id" value="<?= $professor['id'] ?>">
                                                <span class="btn btn-disabled" title="غير مؤهل">
                                                <i class="fas fa-ban"></i> غير مؤهل
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </main>
    </div>
    <!-- Modal dialog for setting conditions -->
<div id="conditionsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="close-modal">&times;</span>
            <h3>تعديل شروط الترقية للأستاذ <span id="professorName"></span></h3>
        </div>
        
        <div id="conditionsContainer">
            <!-- Conditions will be added here dynamically -->
        </div>
        
        <button id="addConditionBtn" class="btn-add-condition">
            <i class="fas fa-plus"></i> إضافة شرط جديد
        </button>
        
        <!-- New file upload section -->
        <div class="file-upload-section">
            <h4>المستندات المطلوبة</h4>
            <div class="file-list" id="fileList">
                <!-- Files will be listed here -->
            </div>
            
            <div class="file-upload-container">
                <input type="file" id="conditionFiles" name="condition_files[]" multiple>
                <label for="conditionFiles" class="file-upload-label">
                    <i class="fas fa-paperclip"></i> إرفاق ملفات المطلوبة
                </label>
            </div>
            
            <!-- <div class="file-requirements">
                <p>يجب إرفاق المستندات التالية:</p>
                <ul>
                    <li>طلب الترقية</li>
                    <li>السيرة الذاتية المحدثة</li>
                    <li>شهادات الخبرة (إن وجدت)</li>
                    <li>المستندات المتعلقة بالتعيين</li>
                </ul>
            </div> -->
        </div>
        
        <div class="modal-actions">
            <button id="cancelConditionsBtn" class="btn-cancel">إلغاء</button>
            <button id="applyConditionsBtn" class="btn-apply">تطبيق</button>
        </div>
    </div>
</div>

<!-- Add a template for file items -->
<template id="fileItemTemplate">
    <div class="file-item">
        <span class="file-name"></span>
        <span class="file-size"></span>
        <button class="remove-file" title="حذف الملف">
            <i class="fas fa-times"></i>
        </button>
    </div>
</template>

<!-- Template for time adjustment condition -->
<template id="timeAdjustmentTemplate">
    <div class="condition-group" data-condition-type="time-adjustment">
        <button class="remove-condition" title="إزالة هذا الشرط">
            <i class="fas fa-times"></i>
        </button>
        <h4>تعديل الأقدمية</h4>
        <div class="form-group">
            <label>نوع التعديل:</label>
            <select class="adjustment-type">
                <option value="add">إضافة وقت</option>
                <option value="subtract">إزالة وقت</option>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>سنوات:</label>
                <input type="number" class="years-value" min="0" value="0">
            </div>
            <div class="form-group">
                <label>أشهر:</label>
                <input type="number" class="months-value" min="0" max="11" value="0">
            </div>
            <div class="form-group">
                <label>أيام:</label>
                <input type="number" class="days-value" min="0" max="30" value="0">
            </div>
        </div>
        <div class="form-group">
            <label>سبب التعديل:</label>
            <input type="text" class="adjustment-reason" placeholder="سبب التعديل...">
        </div>
    </div>
</template>

<!-- Template for sector origin condition -->
<template id="sectorOriginTemplate">
    <div class="condition-group" data-condition-type="sector-origin">
        <button class="remove-condition" title="إزالة هذا الشرط">
            <i class="fas fa-times"></i>
        </button>
        <h4>خبرة قطاعية سابقة</h4>
        <div class="form-group">
            <label>مصدر الخبرة:</label>
            <select class="sector-origin">
                <option value="inside">داخل القطاع (احتساب كامل المدة)</option>
                <option value="outside">خارج القطاع (احتساب نصف المدة)</option>
            </select>
        </div>
        <div class="form-group">
            <label>عدد سنوات الخبرة:</label>
            <input type="number" class="years-experience" min="0" step="0.5" value="0">
        </div>
        <div class="form-group">
            <label>تفاصيل:</label>
            <input type="text" class="experience-details" placeholder="تفاصيل الخبرة السابقة...">
        </div>
    </div>
</template>
    <script>
        
        const formData = [];
        let formbool = false;
        function openTab(tabName) {
            // Hide all tab contents and remove active class from buttons
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
            const markInputA = a.cells[8].querySelector('input');
            const markInputB = b.cells[8].querySelector('input');
            
            const markA = parseFloat(markInputA ? markInputA.value : 0) || 0;
            const markB = parseFloat(markInputB ? markInputB.value : 0) || 0;
            
            if (markB !== markA) {
                return markB - markA;
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
            if(!formbool) {
                formData [index] = new FormData();
            }
        });
        formbool = true;
        
        // Calculate duration categories based on position (دنيا, متوسطة, قصوى)
        updateDurationCategories(tbody, rows);
    });
}

// Function to update duration categories based on relative position in sorted list by grade
function updateDurationCategories(tbody, rows) {
    // Group rows by grade
    const rowsByGrade = {};
    
    rows.forEach(row => {
        const grade = row.cells[2].textContent; // Column for grade (الدرجة الحالية)
        if (!rowsByGrade[grade]) {
            rowsByGrade[grade] = [];
        }
        rowsByGrade[grade].push(row);
    });
    
    // Process each grade separately
    for (const grade in rowsByGrade) {
        const gradeRows = rowsByGrade[grade].filter(row => row.querySelector('.mark'));
        if (gradeRows.length === 0) continue;
        
        const totalRows = gradeRows.length;
        
        // Calculate thresholds for the categories - 40%/40%/20% split
        const firstThreshold = Math.ceil(totalRows * 0.4);    // First 40%
        const secondThreshold = Math.floor(totalRows * 0.8);   // Next 40% (total 80%)
        const lastThreshold = totalRows; // Last 20%


        // Assign duration categories
        gradeRows.forEach((row, index) => {
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
}

// Auto-sort when marks change
document.addEventListener('DOMContentLoaded', function() {
    // Event listener for mark inputs
    const markInputs = document.querySelectorAll('.mark');
    markInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Update the hidden mark input in the promotion form
            const row = this.closest('tr');
            const markInput = row.querySelector('input[name="mark"]');
            if (markInput) {
                markInput.value = this.value || 0;
            }
            
            // Call the existing sorting function
            sortProfessors();
        });
        // Call the existing sorting function
        sortProfessors();
        
        // Also trigger this on initial load to set any default values
        const row = input.closest('tr');
        const markInput = row.querySelector('input[name="mark"]');
        if (markInput) {
            markInput.value = input.value || '0';
        }
    });
    
    // Update mark values before form submission
    const promotionForms = document.querySelectorAll('.promotion-form');
    promotionForms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const row = this.closest('tr');
            const markInputField = row.querySelector('.mark');
            const markHiddenInput = this.querySelector('input[name="mark"]');
            
            if (markInputField && markHiddenInput) {
                markHiddenInput.value = markInputField.value || '0';
            }

            
        });
    });
});




// Global variable to track which professor row is being edited
let currentProfessorRow = null;

// Function to open the conditions modal
function originalOpenConditionsModalFunc(professorRow) {
    const modal = document.getElementById('conditionsModal');
    const professorName = professorRow.cells[1].textContent;
    
    // Store reference to the current professor row
    currentProfessorRow = professorRow;
    
    // Set the professor name in the modal header
    document.getElementById('professorName').textContent = professorName;
    
    // Clear existing conditions
    document.getElementById('conditionsContainer').innerHTML = '';
    
    // Load existing conditions if any
    const existingConditions = professorRow.dataset.conditions;
    if (existingConditions) {
        try {
            const conditions = JSON.parse(existingConditions);
            conditions.forEach(condition => {
                if (condition.type === 'time-adjustment') {
                    addTimeAdjustmentCondition(condition);
                } else if (condition.type === 'sector-origin') {
                    addSectorOriginCondition(condition);
                }
            });
        } catch (e) {
            console.error('Error parsing conditions:', e);
        }
    }
    
    // Show the modal
    modal.style.display = 'block';
}

// Function to close the conditions modal
function closeConditionsModal() {
    const modal = document.getElementById('conditionsModal');
    modal.style.display = 'none';
    currentProfessorRow = null;
}

// Function to add a time adjustment condition
function addTimeAdjustmentCondition(data = null) {
    const container = document.getElementById('conditionsContainer');
    const template = document.getElementById('timeAdjustmentTemplate');
    const clone = document.importNode(template.content, true);
    
    // If data is provided, populate the form fields
    if (data) {
        clone.querySelector('.adjustment-type').value = data.adjustmentType;
        clone.querySelector('.years-value').value = data.years || 0;
        clone.querySelector('.months-value').value = data.months || 0;
        clone.querySelector('.days-value').value = data.days || 0;
        clone.querySelector('.adjustment-reason').value = data.reason || '';
    }
    
    container.appendChild(clone);
    
    // Add event listener to remove button
    const removeBtn = container.lastElementChild.querySelector('.remove-condition');
    removeBtn.addEventListener('click', function() {
        this.closest('.condition-group').remove();
    });
}

// Function to add a sector origin condition
function addSectorOriginCondition(data = null) {
    const container = document.getElementById('conditionsContainer');
    const template = document.getElementById('sectorOriginTemplate');
    const clone = document.importNode(template.content, true);
    
    // If data is provided, populate the form fields
    if (data) {
        clone.querySelector('.sector-origin').value = data.sectorOrigin;
        clone.querySelector('.years-experience').value = data.yearsExperience || 0;
        clone.querySelector('.experience-details').value = data.details || '';
    }
    
    container.appendChild(clone);
    
    // Add event listener to remove button
    const removeBtn = container.lastElementChild.querySelector('.remove-condition');
    removeBtn.addEventListener('click', function() {
        this.closest('.condition-group').remove();
    });
}

// Function to collect conditions data
function collectConditionsData() {
    const container = document.getElementById('conditionsContainer');
    const conditions = [];
    
    // Collect time adjustment conditions
    const timeAdjustments = container.querySelectorAll('[data-condition-type="time-adjustment"]');
    timeAdjustments.forEach(adjustment => {
        conditions.push({
            type: 'time-adjustment',
            adjustmentType: adjustment.querySelector('.adjustment-type').value,
            years: parseInt(adjustment.querySelector('.years-value').value || 0),
            months: parseInt(adjustment.querySelector('.months-value').value || 0),
            days: parseInt(adjustment.querySelector('.days-value').value || 0),
            reason: adjustment.querySelector('.adjustment-reason').value || " "
        });
    });
    
    // Collect sector origin conditions
    const sectorOrigins = container.querySelectorAll('[data-condition-type="sector-origin"]');
    sectorOrigins.forEach(origin => {
        conditions.push({
            type: 'sector-origin',
            sectorOrigin: origin.querySelector('.sector-origin').value,
            yearsExperience: parseFloat(origin.querySelector('.years-experience').value || 0),
            details: origin.querySelector('.experience-details').value || " "
        });
    });
    
    return conditions;
}


// Function to recalculate promotion based on adjusted seniority
function recalculatePromotionWithConditions(row) {
    // Get the rank and adjusted combined seniority
    const rank = row.dataset.rank;
    const durationType = row.cells[9].textContent;
    const adjustedCombined = row.cells[7].textContent; // الأقدمية الكلية cell
    
    // Parse the adjusted combined value
    const [totalYears, totalMonths, totalDays] = parseDuration(adjustedCombined);
    
    // Convert to total months for calculation
    const totalMonthsAll = (totalYears * 12) + totalMonths + (totalDays / 30);
    
    // Get required duration for this rank and type
    let requiredDuration;
    if (rank === 'أستاذ') {
        requiredDuration = 2.5; // Fixed for professors
    } else {
        // For other ranks, get from the cell or use default
        if (durationType === 'دنيا') {
            requiredDuration = 2.5;
        } else if (durationType === 'متوسطة') {
            requiredDuration = 3;
        } else {
            requiredDuration = 3.5;
        }
    }
    
    // Convert required duration to months
    const requiredMonths = requiredDuration * 12;
    
    // Get current grade and grade_p
    const currentGrade = parseInt(row.cells[2].textContent) || 0;
    const currentGradeP = parseInt(row.cells[3].textContent) || 1680;


    
    // Calculate new grade and remaining time
    let newGrade = currentGrade;
    let newGradeP = currentGradeP;
    let remainingMonths = totalMonthsAll;
    
    // Check if eligible for promotion
    if (totalMonthsAll >= requiredMonths) {
        // Calculate how many promotions
        while (remainingMonths >= requiredMonths) {
            remainingMonths -= requiredMonths;
            newGrade++;
            newGradeP += 84;
        }
        
        // Calculate remaining time
        const remainingYears = Math.floor(remainingMonths / 12);
        const remainingMonthsRemainder = Math.floor(remainingMonths % 12);
        const remainingDays = Math.round((remainingMonths % 1) * 30);
        const remaining = `${remainingYears}y ${remainingMonthsRemainder}m ${remainingDays}d`;
        
        // Calculate eligibility date
        const today = new Date();
        const currentYear = today.getFullYear();
        const eligibilityDate = new Date(currentYear, 11, 31); // Dec 31 of current year
        eligibilityDate.setFullYear(eligibilityDate.getFullYear() - remainingYears);
        eligibilityDate.setMonth(eligibilityDate.getMonth() - remainingMonthsRemainder);
        eligibilityDate.setDate(eligibilityDate.getDate() - remainingDays);
        
        // Format eligibility date as YYYY-MM-DD
        const formattedEligibilityDate = eligibilityDate.toISOString().split('T')[0];
        
        // Update the row cells
        row.cells[10].textContent = newGrade;
        row.cells[11].textContent = newGradeP;
        row.cells[12].textContent = formattedEligibilityDate;
        row.cells[13].textContent = remaining;

        
        // Update promotion form if it exists
        const promotionForm = row.querySelector('.promotion-form');
        const actions = row.querySelector('.actions');
        if (promotionForm) {
            const newGradeInput = promotionForm.querySelector('input[name="new_grade"]');
            const professorId = actions.querySelector('input[name="professor_id"]');
            console.log(professorId);
            if (newGradeInput) {
                newGradeInput.value = newGrade;
            }
            
            const eligibilityDateInput = promotionForm.querySelector('input[name="eligibility_date"]');
            if (eligibilityDateInput) {
                eligibilityDateInput.value = formattedEligibilityDate;
            }
            
            const promoteBtn = promotionForm.querySelector('.btn-promote');
            if (promoteBtn) {
                promoteBtn.innerHTML = `<i class="fas fa-arrow-up"></i> ترقية (${newGrade - currentGrade} درجة)`;
                promoteBtn.title = `ترقية الى الدرجة ${newGrade}`;
            }
        }
        
        // Enable promotion elements if eligible
        const actionsCell = row.cells[15];
        
        if (newGrade > currentGrade) {
            // Replace the disabled button with a promotion form if needed
            if (!promotionForm) {
                actionsCell.innerHTML = `
                    <form method="post" action="process_promotion.php" class="promotion-form" enctype="multipart/form-data">
                        <input type="hidden" name="professor_id" value="${row.querySelector('input[name="professor_id"]')?.value || ''}">
                        <input type="hidden" name="previous_grade" value="${currentGrade}">
                        <input type="hidden" name="new_grade" value="${newGrade}">
                        <input type="hidden" name="effective_date" value="${row.cells[4].textContent}">
                        <input type="hidden" name="eligibility_date" value="${formattedEligibilityDate}">
                        <input type="hidden" name="dur" value="${durationType}">
                        <input type="hidden" name="mark" value="0">
                        <input type="hidden" name="total_seniority" value="${row.cells[7].textContent}">
                        <input type="hidden" name="activity_date" value="${formattedEligibilityDate}">
                        <input type="hidden" name="remaining_seniority" value="${remaining}">
                        <input type="hidden" name="note" value="${row.cells[14].textContent}">
                        
                        <button type="submit" class="btn btn-promote" title="ترقية الى الدرجة ${newGrade}">
                            <i class="fas fa-arrow-up"></i> ترقية (${newGrade - currentGrade} درجة)
                        </button>
                    </form>
                `;
            }
        } else {
            // No promotion needed, update display
            actionsCell.innerHTML = `
                <span class="btn btn-disabled" title="غير مؤهل للترقية">
                    <i class="fas fa-check"></i> مكتمل
                </span>
            `;
        }
    } else {
        // Not eligible for promotion
        row.cells[10].textContent = '-';
        row.cells[11].textContent = '-';
        row.cells[12].textContent = 'Not eligible';
        row.cells[13].textContent = '_____';
        
        // Update action cell with disabled button
        const actionsCell = row.cells[15];
        actionsCell.innerHTML = `
            <span class="btn btn-disabled" title="غير مؤهل">
                <i class="fas fa-ban"></i> غير مؤهل
            </span>
        `;
    }
}


// Helper function to parse a duration string (e.g., "2y 3m 15d")
function parseDuration(durationStr) {
    const yearMatch = durationStr.match(/(\d+)y/);
    const monthMatch = durationStr.match(/(\d+)m/);
    const dayMatch = durationStr.match(/(\d+)d/);
    
    const years = yearMatch ? parseInt(yearMatch[1]) : 0;
    const months = monthMatch ? parseInt(monthMatch[1]) : 0;
    const days = dayMatch ? parseInt(dayMatch[1]) : 0;
    
    return [years, months, days];
}

// Helper function to format adjustment for display
function formatAdjustment(years, months, days) {
    const parts = [];
    if (years > 0) parts.push(`${years} سنة`);
    if (months > 0) parts.push(`${months} شهر`);
    if (days > 0) parts.push(`${days} يوم`);
    return parts.join(' و ');
}

// Set up event listeners when the document is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Event listener for "Add Condition" button
    document.getElementById('addConditionBtn').addEventListener('click', function() {
        // Show a dropdown or modal to select condition type
        const conditionType = prompt('اختر نوع الشرط:\n1. تعديل الأقدمية\n2. خبرة قطاعية سابقة');
        
        if (conditionType === '1') {
            addTimeAdjustmentCondition();
        } else if (conditionType === '2') {
            addSectorOriginCondition();
        }
    });
    
    // Event listeners for modal buttons
    document.getElementById('applyConditionsBtn').addEventListener('click', originalApplyConditions);
    document.getElementById('cancelConditionsBtn').addEventListener('click', closeConditionsModal);
    document.querySelector('.close-modal').addEventListener('click', closeConditionsModal);
    
    // Close the modal if clicking outside of it
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('conditionsModal');
        if (event.target === modal) {
            closeConditionsModal();
        }
    });
    
    // Add condition buttons to the ملاحظات column in the table
    const tables = document.querySelectorAll('.promotion-table');
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const notesCell = row.cells[14]; // ملاحظات cell
            
            // Create the conditions button
            const conditionsBtn = document.createElement('button');
            conditionsBtn.className = 'btn btn-conditions';
            conditionsBtn.innerHTML = '<i class="fas fa-cog"></i> تعديل الشروط';
            conditionsBtn.addEventListener('click', function() {
                originalOpenConditionsModal(row);
            });
            
            // Add button to the notes cell
            notesCell.appendChild(conditionsBtn);
        });
    });
});

// Array to store file objects
let uploadedFiles = [];
let fileList;
// Add JavaScript for handling file uploads
document.addEventListener('DOMContentLoaded', function() {
    // File upload handling for conditions modal
    const fileInput = document.getElementById('conditionFiles');
    fileList = document.getElementById('fileList');
    const fileItemTemplate = document.getElementById('fileItemTemplate');
    
    
    // Event listener for file input change
    fileInput.addEventListener('change', function(event) {
        const files = event.target.files;
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Add file to the array
            uploadedFiles.push(file);
            
            // Create file item in the list
            const fileItem = document.importNode(fileItemTemplate.content, true);
            const fileItemElement = fileItem.querySelector('.file-item');
            
            // Set file information
            fileItemElement.querySelector('.file-name').textContent = file.name;
            fileItemElement.querySelector('.file-size').textContent = formatFileSize(file.size);
            
            // Add data attribute to link with the file in array
            fileItemElement.dataset.fileIndex = uploadedFiles.length - 1;
            
            // Add event listener to remove button
            fileItemElement.querySelector('.remove-file').addEventListener('click', function() {
                const fileIndex = parseInt(fileItemElement.dataset.fileIndex);
                
                // Remove file from array
                uploadedFiles.splice(fileIndex, 1);
                
                // Remove file item from list
                fileItemElement.remove();
                
                // Update indexes for remaining file items
                updateFileIndexes();
            });
            
            fileList.appendChild(fileItem);
        }
        
        // Clear file input
        fileInput.value = '';
    });
});
    
    // Function to format file size
    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        } else if (bytes < 1048576) {
            return (bytes / 1024).toFixed(1) + ' KB';
        } else {
            return (bytes / 1048576).toFixed(1) + ' MB';
        }
    }
    
    // Function to update file indexes after removal
    function updateFileIndexes() {
        const fileItems = fileList.querySelectorAll('.file-item');
        fileItems.forEach((item, index) => {
            item.dataset.fileIndex = index;
        });
    }
    
// Redefine the global function
function originalApplyConditions() {
    if (!currentProfessorRow) return;
    
    const conditions = collectConditionsData();
    
    // Store conditions in the row's dataset
    currentProfessorRow.dataset.conditions = JSON.stringify(conditions);
    
    // Apply the conditions to update the combined seniority
    let combinedCell = currentProfessorRow.cells[7]; // الأقدمية الكلية cell
    let originalCombined = combinedCell.dataset.originalCombined;
    
    // If we don't have original stored, store it now
    if (!originalCombined) {
        originalCombined = combinedCell.textContent;
        combinedCell.dataset.originalCombined = originalCombined;
    }
    
    // Parse the original combined value 
    let [years, months, days] = parseDuration(originalCombined);
    
    // Apply each condition
    let adjustmentDescription = [];
    
    conditions.forEach(condition => {
        if (condition.type === 'time-adjustment') {
            // Apply time adjustments
            const { adjustmentType, years: adjYears, months: adjMonths, days: adjDays, reason } = condition;
            
            if (adjustmentType === 'add') {
                // Add time
                days += adjDays;
                months += adjMonths;
                years += adjYears;
                
                if (reason) {
                    adjustmentDescription.push(`إضافة ${formatAdjustment(adjYears, adjMonths, adjDays)}: ${reason}`);
                }
            } else {
                // Subtract time
                days -= adjDays;
                months -= adjMonths;
                years -= adjYears;
                
                if (reason) {
                    adjustmentDescription.push(`إزالة ${formatAdjustment(adjYears, adjMonths, adjDays)}: ${reason}`);
                }
            }
        } else if (condition.type === 'sector-origin') {
            // Apply sector origin adjustments
            const { sectorOrigin, yearsExperience, details } = condition;
            
            // Convert years to months for easier calculation
            let monthsToAdd = yearsExperience * 12;
            
            // If outside sector, only add half
            if (sectorOrigin === 'outside') {
                monthsToAdd = monthsToAdd / 2;
                if (details) {
                    adjustmentDescription.push(`خبرة خارج القطاع (${yearsExperience} سنوات، تحتسب بالنصف): ${details}`);
                }
            } else {
                if (details) {
                    adjustmentDescription.push(`خبرة داخل القطاع (${yearsExperience} سنوات): ${details}`);
                }
            }
            
            // Add the calculated months
            months += Math.floor(monthsToAdd);
            // Add remaining days (approximation: 30 days per month)
            days += Math.round((monthsToAdd - Math.floor(monthsToAdd)) * 30);
        }
    });
    
    // Normalize the duration (handle overflow)
    if (days < 0) {
        const monthsToSubtract = Math.ceil(Math.abs(days) / 30);
        months -= monthsToSubtract;
        days += monthsToSubtract * 30;
    }
    
    if (days >= 30) {
        months += Math.floor(days / 30);
        days = days % 30;
    }
    
    if (months < 0) {
        const yearsToSubtract = Math.ceil(Math.abs(months) / 12);
        years -= yearsToSubtract;
        months += yearsToSubtract * 12;
    }
    
    if (months >= 12) {
        years += Math.floor(months / 12);
        months = months % 12;
    }
    
    // Update the combined seniority cell
    const adjustedCombined = `${years}y ${months}m ${days}d`;
    combinedCell.textContent = adjustedCombined;
    

    
    // Update the notes cell with the adjustment descriptions
    const notesCell = currentProfessorRow.cells[14]; // ملاحظات cell
    if (adjustmentDescription.length > 0) {
        notesCell.innerHTML = adjustmentDescription.join('<br>');
    } else {
        notesCell.innerHTML = '';
    }
    
    // Recalculate promotion eligibility with the new values
    recalculatePromotionWithConditions(currentProfessorRow);
    
    // Make sure we have a reference to the fileList
    const fileList = document.getElementById('fileList');
    
    // Process file uploads
    if (uploadedFiles && uploadedFiles.length > 0) {
        const professorRow = currentProfessorRow;
        const professorId = currentProfessorRow.querySelector('input[name="professor_id"]').value;

        formData[professorRow.cells[0].textContent-1].append('professor_id', professorId);
        
        // Add files to FormData
        uploadedFiles.forEach(file => {
            formData[professorRow.cells[0].textContent-1].append('condition_files[]', file);
        });
        
        // Log upload attempt
        console.log('Uploading files for professor ID:', professorId);
        console.log('Number of files being uploaded:', uploadedFiles.length);
        
        // Send files to server
        fetch('upload_condition_files.php', {
                method: 'POST',
                body: formData[professorRow.cells[0].textContent-1]
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Files uploaded successfully');

                    // Clear file list after successful upload
                    fileList.innerHTML = '';
                    uploadedFiles = [];

                    // Update notes with file information
                    const notesCell = professorRow.cells[14]; // ملاحظات cell
                    if (data.files && data.files.length > 0) {
                        let fileInfo = '<div class="uploaded-files"><h4>المستندات المرفقة:</h4><ul>';
                        data.files.forEach(file => {
                            fileInfo += `<li>${file.name} (${formatFileSize(file.size)})</li>`;
                        });
                        fileInfo += '</ul></div>';

                        // Append file info to existing notes
                        notesCell.innerHTML += fileInfo;
                    }
                } else {
                    console.error('Error uploading files:', data.error);
                    alert('حدث خطأ أثناء رفع الملفات: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error uploading files:', error);
                alert('حدث خطأ أثناء رفع الملفات');
            });
        
    }else {
        console.log('No files to upload');
    }
    closeConditionsModal();
}

function originalOpenConditionsModal(professorRow) {
    // Call the original function
    originalOpenConditionsModalFunc(professorRow);
    
}
</script>
</body>
</html>