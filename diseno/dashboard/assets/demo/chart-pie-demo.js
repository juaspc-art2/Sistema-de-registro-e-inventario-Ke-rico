// Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#292b2c';

// Pie Chart Example - Ventas por sucursal Ke-Rico!
var ctx = document.getElementById("myPieChart");
var myPieChart = new Chart(ctx, {
  type: 'pie',
  data: {
    labels: ["Bogotá", "Medellín", "Cali", "Barranquilla"],
    datasets: [{
      data: [42, 26, 18, 14],
      backgroundColor: ['#f5821f', '#ffc107', '#2a1a10', '#ffffff'],
      borderColor: ['#f5821f', '#ffc107', '#2a1a10', '#dddddd'],
      borderWidth: 1,
    }],
  },
});
