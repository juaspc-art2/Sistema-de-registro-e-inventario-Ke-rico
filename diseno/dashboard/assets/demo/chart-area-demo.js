// Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#292b2c';

// Area Chart Example - Ventas semanales de Ke-Rico!
var ctx = document.getElementById("myAreaChart");
var myLineChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"],
    datasets: [{
      label: "Ventas ($COP)",
      lineTension: 0.3,
      backgroundColor: "rgba(245,130,31,0.2)",
      borderColor: "rgba(245,130,31,1)",
      pointRadius: 5,
      pointBackgroundColor: "rgba(245,130,31,1)",
      pointBorderColor: "rgba(255,255,255,0.8)",
      pointHoverRadius: 5,
      pointHoverBackgroundColor: "rgba(245,130,31,1)",
      pointHitRadius: 50,
      pointBorderWidth: 2,
      data: [980000, 1120000, 1050000, 1230000, 1580000, 2140000, 1850000],
    }],
  },
  options: {
    scales: {
      xAxes: [{
        time: {
          unit: 'date'
        },
        gridLines: {
          display: false
        },
        ticks: {
          maxTicksLimit: 7
        }
      }],
      yAxes: [{
        ticks: {
          min: 0,
          max: 2500000,
          maxTicksLimit: 5,
          callback: function(value) {
            return '$' + value.toLocaleString('es-CO');
          }
        },
        gridLines: {
          color: "rgba(0, 0, 0, .125)",
        }
      }],
    },
    legend: {
      display: false
    }
  }
});
