<?php
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
// existing questions -> JS seed
$seed = [];
foreach ($questions as $q) {
    $seed[] = [
        'question' => $q['question'],
        'qtype'    => $q['qtype'],
        'options'  => (array)json_decode($q['options'] ?? '[]', true),
        'correct'  => array_map('intval',(array)json_decode($q['correct'] ?? '[]', true)),
    ];
}
?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Edit quiz</h4>
    <div class="text-muted small"><?= e($module['course_code']) ?> — <?= e($module['course_title']) ?></div></div>
  <a href="?r=content" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to content</a>
</div>

<?php if ($flash): ?><div class="alert alert-success py-2"><?= e($flash) ?></div><?php endif; ?>

<form method="post" action="?r=quiz_save" id="quizForm">
  <input type="hidden" name="module_id" value="<?= (int)$module['id'] ?>">
  <div class="card p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-8"><label class="form-label small fw-semibold">Quiz title</label>
        <input name="title" class="form-control" value="<?= e($module['title']) ?>" required></div>
      <div class="col-md-4"><label class="form-label small fw-semibold">Pass mark (%)</label>
        <input name="pass_mark" type="number" min="1" max="100" class="form-control" value="<?= (int)$module['pass_mark'] ?>"></div>
    </div>
  </div>

  <div id="builder"></div>

  <div class="d-flex gap-2 mb-4">
    <button type="button" class="btn btn-outline-danger" onclick="addQuestion()"><i class="bi bi-plus-circle"></i> Add question</button>
    <button type="submit" class="btn btn-anb ms-auto"><i class="bi bi-save"></i> Save quiz</button>
  </div>
</form>

<script>
var questions = <?= json_encode($seed, JSON_UNESCAPED_UNICODE) ?>;
if (!questions.length) questions = [{question:'',qtype:'single',options:['',''],correct:[]}];

function render(){
  var b = document.getElementById('builder'); b.innerHTML='';
  questions.forEach(function(q,i){
    var card = document.createElement('div'); card.className='card p-3 mb-3';
    var opts = '';
    if (q.qtype==='truefalse'){
      opts = '<div class="small text-muted mb-1">Correct answer</div>'+
        ['True','False'].map(function(lbl,oi){
          return '<label class="me-3"><input type="radio" name="q['+i+'][correct_single]" value="'+oi+'" '+(q.correct.indexOf(oi)>-1?'checked':'')+'> '+lbl+'</label>';
        }).join('');
    } else {
      opts = '<div class="small text-muted mb-1">Options ('+(q.qtype==='multi'?'tick all correct':'select the correct one')+')</div>';
      q.options.forEach(function(ot,oi){
        var ctrl = q.qtype==='multi'
          ? '<input type="checkbox" name="q['+i+'][correct]['+oi+']" '+(q.correct.indexOf(oi)>-1?'checked':'')+'>'
          : '<input type="radio" name="q['+i+'][correct_single]" value="'+oi+'" '+(q.correct.indexOf(oi)>-1?'checked':'')+'>';
        opts += '<div class="input-group mb-1">'
          + '<span class="input-group-text">'+ctrl+'</span>'
          + '<input class="form-control" name="q['+i+'][opt]['+oi+']" value="'+esc(ot)+'" oninput="questions['+i+'].options['+oi+']=this.value" placeholder="Option '+(oi+1)+'">'
          + '<button type="button" class="btn btn-outline-secondary" onclick="delOption('+i+','+oi+')">&times;</button>'
          + '</div>';
      });
      opts += '<button type="button" class="btn btn-sm btn-link px-0" onclick="addOption('+i+')">+ add option</button>';
    }
    card.innerHTML =
      '<div class="d-flex justify-content-between mb-2"><span class="fw-semibold">Question '+(i+1)+'</span>'
      + '<button type="button" class="btn btn-sm btn-outline-danger" onclick="delQuestion('+i+')"><i class="bi bi-trash"></i></button></div>'
      + '<input class="form-control mb-2" name="q['+i+'][question]" value="'+esc(q.question)+'" oninput="questions['+i+'].question=this.value" placeholder="Enter the question" required>'
      + '<select class="form-select form-select-sm mb-2" style="max-width:220px" onchange="setType('+i+',this.value)">'
      +   '<option value="single"'+(q.qtype==='single'?' selected':'')+'>Single choice</option>'
      +   '<option value="multi"'+(q.qtype==='multi'?' selected':'')+'>Multiple choice</option>'
      +   '<option value="truefalse"'+(q.qtype==='truefalse'?' selected':'')+'>True / False</option>'
      + '</select>'
      + opts;
    b.appendChild(card);
  });
}
function esc(s){return (s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');}
function addQuestion(){questions.push({question:'',qtype:'single',options:['',''],correct:[]});render();}
function delQuestion(i){questions.splice(i,1);if(!questions.length)addQuestion();else render();}
function addOption(i){questions[i].options.push('');render();}
function delOption(i,oi){questions[i].options.splice(oi,1);questions[i].correct=[];render();}
function setType(i,t){questions[i].qtype=t;questions[i].correct=[];if(t!=='truefalse'&&questions[i].options.length<2)questions[i].options=['',''];render();}
render();
</script>
