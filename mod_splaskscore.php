<?php
defined('_JEXEC') or die;

$token = $params->get('splask_token', '');
?>

<style>
.splask-meter {
  position: relative;
  width: 150px;
  height: 150px;
  margin: 0 auto;
}
.splask-meter svg {
  transform: rotate(-90deg);
}
.splask-meter circle {
  fill: none;
  stroke-width: 12;
}
.splask-meter .bg {
  stroke: #eee;
}
.splask-meter .progress {
  stroke-linecap: round;
  transition: stroke-dashoffset 1s ease-out, stroke 0.5s ease;
}
.splask-meter .value {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 1.4em;
  font-weight: bold;
}
#evaluation-text {
  font-size: 1.6em;
  font-weight: bold;
  text-transform: uppercase;
  margin-top: 1rem;
}
</style>

<div class="card mt-3 text-center">
  <div class="card-header bg-dark text-white">Markah Penilaian SPLaSK :</div>
  <div class="card-body">
    <div class="splask-meter mb-2">
      <svg width="150" height="150">
        <circle class="bg" cx="75" cy="75" r="60"/>
        <circle id="progress-circle" class="progress" cx="75" cy="75" r="60" stroke="#ccc" stroke-dasharray="377" stroke-dashoffset="377"/>
      </svg>
      <div class="value" id="score-text">0%</div>
    </div>
    <div id="evaluation-text">Memuat...</div>
    <p class="mt-3">Kemaskini Terakhir: <span id="splask-date">---</span></p>
    <p>Semakan seterusnya: <span id="splask-next">---</span></p>
    <p><a href="#" target="_blank" id="splask-link">Lihat Pengesahan Penuh</a></p>
  </div>
</div>

<script>
function getEvaluation(score) {
  if (score >= 95) return 'A';
  if (score >= 91) return 'B';
  if (score >= 86) return 'C';
  return 'GAGAL';
}
function getColor(score) {
  if (score >= 95) return '#28a745'; // Hijau A
  if (score >= 91) return '#007bff'; // Biru B
  if (score >= 86) return '#ffc107'; // Kuning C
  return '#dc3545'; // Merah GAGAL
}
function formatTo12Hour(dateStr) {
  const [day, month, rest] = dateStr.split('/');
  const [year, time] = rest.split(' ');
  const [hour, minute] = time.split(':');
  const h = parseInt(hour);
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hr12 = h % 12 === 0 ? 12 : h % 12;
  return `${hr12}:${minute} ${ampm} ${day}/${month}/${year}`;
}

fetch("https://splask-api.jdn.gov.my/api/get_my_score", {
  method: "POST",
  headers: {
    "Content-Type": "application/json"
  },
  body: JSON.stringify({ _token: "<?php echo $token; ?>" })
})
.then(res => res.json())
.then(data => {
  if (data.status) {
    const score = data.final_score;
    const circle = document.getElementById("progress-circle");
    const offset = 377 - (377 * score / 100);
    const color = getColor(score);

    circle.style.stroke = color;
    circle.style.strokeDashoffset = offset;

    document.getElementById("score-text").textContent = score + "%";
    document.getElementById("evaluation-text").textContent = getEvaluation(score).toUpperCase();
    document.getElementById("splask-date").textContent = formatTo12Hour(data.last_check);

    const nextCheck = new Date();
    nextCheck.setDate(nextCheck.getDate() + 1);
    document.getElementById("splask-next").textContent = nextCheck.toLocaleDateString('en-GB');
    document.getElementById("splask-link").href = data.verification_url;
  } else {
    document.getElementById("evaluation-text").textContent = "MARKAH TIDAK DIJUMPAI";
  }
})
.catch(err => {
  document.getElementById("evaluation-text").textContent = "RALAT SAMBUNGAN API";
});
</script>
