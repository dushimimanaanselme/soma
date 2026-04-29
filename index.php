 <?php
session_start();

// 1. DATABASE CONNECTION
$conn = new mysqli("localhost", "root", "", "quiz_system_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$msg = "";
$role = $_GET['role'] ?? "";

/* =========================
   2. TEACHER AUTHENTICATION
========================= */
if (isset($_POST['register_teacher'])) {
    $u = $conn->real_escape_string($_POST['tUser']);
    $p = $conn->real_escape_string($_POST['tPass']);
    $check = $conn->query("SELECT * FROM teacher WHERE username='$u'");
    if ($check->num_rows > 0) { $msg = "error:Username exists!"; } 
    else { $conn->query("INSERT INTO teacher (username, password) VALUES ('$u', '$p')"); $msg = "success:Registered! Please login."; }
}

if (isset($_POST['login_teacher'])) {
    $u = $conn->real_escape_string($_POST['tUser']);
    $p = $conn->real_escape_string($_POST['tPass']);
    $result = $conn->query("SELECT * FROM teacher WHERE username='$u' AND password='$p'");
    if ($result->num_rows > 0) { $_SESSION['teacher_name'] = $u; header("Location: ?role=teacher"); exit(); } 
    else { $msg = "error:Invalid login."; }
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: ?role=teacher"); exit(); }

/* =========================
   3. TEACHER DASHBOARD
========================= */
if (isset($_SESSION['teacher_name']) && isset($_POST['save_question'])) {
    $q = $conn->real_escape_string($_POST['q']);
    $a = $conn->real_escape_string($_POST['a']);
    $b = $conn->real_escape_string($_POST['b']);
    $c = $conn->real_escape_string($_POST['c']);
    $d = $conn->real_escape_string($_POST['d']);
    $cor = $conn->real_escape_string($_POST['correct']);
    $m = (int)$_POST['marks'];
    $conn->query("INSERT INTO questions (question, optionA, optionB, optionC, optionD, correct_answer, marks) VALUES ('$q', '$a', '$b', '$c', '$d', '$cor', '$m')");
}

/* =========================
   4. STUDENT QUIZ LOGIC
========================= */
$quiz_results = null;
if (isset($_POST['submit_quiz'])) {
    $name = $conn->real_escape_string($_POST['studentName']);
    $class = $conn->real_escape_string($_POST['studentClass']);
    $score = 0; $total_marks = 0;
    $breakdown = [];

    $qs = $conn->query("SELECT * FROM questions");
    while ($row = $qs->fetch_assoc()) {
        $qid = $row['id'];
        $total_marks += $row['marks'];
        $student_ans = $_POST['ans_'.$qid] ?? "";
        $is_correct = ($student_ans === $row['correct_answer']);
        
        if ($is_correct) { $score += $row['marks']; }
        
        $breakdown[] = [
            'q' => $row['question'],
            'student' => $student_ans,
            'correct' => $row['correct_answer'],
            'is_correct' => $is_correct
        ];
    }

    $percent = ($total_marks > 0) ? ($score / $total_marks) * 100 : 0;
    $status = ($percent >= 80) ? "Excellent" : (($percent >= 50) ? "Pass" : "Needs Improvement");
    
    // Save Result to DB
    $conn->query("INSERT INTO results (student_name, class, score, total, percent) VALUES ('$name', '$class', '$score', '$total_marks', '$percent')");
    
    $quiz_results = ['name' => $name, 'class' => $class, 'score' => $score, 'total' => $total_marks, 'percent' => $percent, 'status' => $status, 'breakdown' => $breakdown];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mureke Dusome System</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0d0f1a; --surface: #13162a; --surface2: #1a1e35; --border: rgba(255,255,255,0.1); --teacher: #f5a623; --student: #4fc3f7; --text: #e8eaf6; --danger: #ff5370; --success: #69f0ae; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); padding: 20px; line-height: 1.5; }
        .container { max-width: 800px; margin: 40px auto; }
        .logo { font-family: 'Syne', sans-serif; font-size: 2.5rem; text-align: center; margin-bottom: 40px; }
        .logo span { color: var(--student); }
        .panel { background: var(--surface); border-radius: 20px; padding: 30px; border: 1px solid var(--border); box-shadow: 0 15px 35px rgba(0,0,0,0.4); margin-bottom: 20px; }
        input, textarea, select { width: 100%; padding: 12px; margin-bottom: 10px; background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; color: white; outline: none; }
        .btn { display: inline-block; width: 100%; padding: 14px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; transition: 0.2s; text-align: center; text-decoration: none; }
        .btn-teacher { background: var(--teacher); color: #000; }
        .btn-student { background: var(--student); color: #000; }
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .alert.error { background: rgba(255,83,112,0.1); color: var(--danger); border: 1px solid var(--danger); }
        .alert.success { background: rgba(105,240,174,0.1); color: var(--success); border: 1px solid var(--success); }
        .q-card { background: var(--surface2); padding: 20px; border-radius: 15px; margin-bottom: 20px; border-left: 4px solid var(--student); }
        .feedback { font-size: 0.9rem; margin-top: 10px; padding: 10px; border-radius: 8px; }
        .feedback.wrong { background: rgba(255,83,112,0.1); color: var(--danger); }
        .feedback.correct { background: rgba(105,240,174,0.1); color: var(--success); }
    </style>
</head>
<body>

<div class="container">
    <div class="logo">MUREKE<span><br>DUSOME</span></div>

    <?php if ($role === ""): ?>
        <div style="display:flex; gap:20px; justify-content:center; flex-wrap:wrap;">
            <a href="?role=teacher" class="panel" style="text-decoration:none; color:white; width:280px; text-align:center;"><h3>👨‍🏫 Teacher</h3></a>
            <a href="?role=student" class="panel" style="text-decoration:none; color:white; width:280px; text-align:center;"><h3>👨‍🎓 Student</h3></a>
        </div>

    <?php elseif ($role === "teacher"): ?>
        <div class="panel">
            <?php if (!isset($_SESSION['teacher_name'])): ?>
                <?php if($msg) { $m = explode(':', $msg); echo "<div class='alert {$m[0]}'>{$m[1]}</div>"; } ?>
                <form method="POST">
                    <h2>Teacher Login</h2>
                    <input type="text" name="tUser" placeholder="Username" required>
                    <input type="password" name="tPass" placeholder="Password" required>
                    <button name="login_teacher" class="btn btn-teacher">Login</button>
                    <button name="register_teacher" class="btn" style="background:none; color:var(--teacher); margin-top:10px;">Or Register New Account</button>
                </form>
            <?php else: ?>
                <h2>Welcome, <?php echo $_SESSION['teacher_name']; ?></h2>
                <form method="POST" style="margin-top:20px;">
                    <textarea name="q" placeholder="Enter Question" required></textarea>
                    <input type="text" name="a" placeholder="Option A" required>
                    <input type="text" name="b" placeholder="Option B" required>
                    <input type="text" name="c" placeholder="Option C" required>
                    <input type="text" name="d" placeholder="Option D" required>
                    <select name="correct" required><option value="A">Correct: A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select>
                    <input type="number" name="marks" value="1" min="1">
                    <button name="save_question" class="btn btn-teacher">Create the Question</button>
                </form>
                <a href="?logout=1" style="color:var(--danger); display:block; text-align:center; margin-top:20px;">Logout</a>
            <?php endif; ?>
        </div>

    <?php elseif ($role === "student"): ?>
        <?php if ($quiz_results): ?>
            <div class="panel" style="text-align:center;">
                <h2>Results for <?php echo $quiz_results['name']; ?></h2>
                <p>Class: <?php echo $quiz_results['class']; ?></p>
                <h1 style="font-size:4rem; color:var(--student);"><?php echo round($quiz_results['percent'], 1); ?>%</h1>
                <h3>Marks: <?php echo $quiz_results['score']; ?> / <?php echo $quiz_results['total']; ?></h3>
                <div class="alert success" style="font-size:1.5rem;"><?php echo $quiz_results['status']; ?></div>
                
                <div style="text-align:left; margin-top:30px;">
                    <?php foreach ($quiz_results['breakdown'] as $item): ?>
                        <div class="q-card" style="border-left-color: <?php echo $item['is_correct'] ? 'var(--success)' : 'var(--danger)'; ?>">
                            <p><b>Q: <?php echo $item['q']; ?></b></p>
                            <?php if ($item['is_correct']): ?>
                                <div class="feedback correct">Correct! You chose <?php echo $item['student']; ?></div>
                            <?php else: ?>
                                <div class="feedback wrong">Wrong! You chose <?php echo $item['student']; ?>. The correct answer was <b><?php echo $item['correct']; ?></b></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="?role=student" class="btn btn-student">Try Again</a>
            </div>
        <?php else: ?>
            <div class="panel">
                <form method="POST">
                    <h2>Student Information</h2>
                    <input type="text" name="studentName" placeholder="Enter Full Name" required>
                    <input type="text" name="studentClass" placeholder="Enter Your Class" required>
                    <hr style="opacity:0.1; margin:20px 0;">
                    <?php 
                    $qs = $conn->query("SELECT * FROM questions");
                    while ($row = $qs->fetch_assoc()): ?>
                        <div class="q-card">
                            <p style="margin-bottom:10px;"><b><?php echo htmlspecialchars($row['question']); ?></b> (<?php echo $row['marks']; ?> Marks)</p>
                            <label style="display:block;"><input type="radio" name="ans_<?php echo $row['id']; ?>" value="A" required> A) <?php echo $row['optionA']; ?></label>
                            <label style="display:block;"><input type="radio" name="ans_<?php echo $row['id']; ?>" value="B"> B) <?php echo $row['optionB']; ?></label>
                            <label style="display:block;"><input type="radio" name="ans_<?php echo $row['id']; ?>" value="C"> C) <?php echo $row['optionC']; ?></label>
                            <label style="display:block;"><input type="radio" name="ans_<?php echo $row['id']; ?>" value="D"> D) <?php echo $row['optionD']; ?></label>
                        </div>
                    <?php endwhile; ?>
                    <button name="submit_quiz" class="btn btn-student">Submit My Quiz</button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <div style="text-align:center;"><a href="index.php" style="color:var(--text); opacity:0.5; text-decoration:none;">← Back to Menu</a></div>
</div>

</body>
</html>