/**
 * WyvernPress Dashboard JavaScript
 *
 * Initializes Chart.js visualizations for the usage dashboard.
 *
 * @param   $
 * @package WyvernPress
 * @since 1.0.0
 */

( function ( $ ) {
  'use strict';

  /**
   * Initialize dashboard charts
   */
  function initDashboard() {
    // Ensure Chart.js is loaded.
    if ( typeof Chart === 'undefined' ) {
      console.error( 'Chart.js is not loaded' );
      return;
    }

    // Ensure we have data.
    if ( typeof wyverncssData === 'undefined' ) {
      console.error( 'Dashboard data is not available' );
      return;
    }

    // Initialize Requests Chart (Line Chart).
    const requestsCanvas = document.getElementById( 'wyverncss-requests-chart' );
    if ( requestsCanvas ) {
      const requestsCtx = requestsCanvas.getContext( '2d' );

      // Determine which chart to show based on active tab.
      let chartData = wyverncssData.dailyChart;
      if ( window.location.href.includes( 'period=weekly' ) ) {
        chartData = wyverncssData.weeklyChart;
      } else if ( window.location.href.includes( 'period=monthly' ) ) {
        chartData = wyverncssData.monthlyChart;
      }

      new Chart( requestsCtx, {
          type: 'line',
          data: chartData,
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: true,
                position: 'top',
              },
              tooltip: {
                mode: 'index',
                intersect: false,
            },
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                stepSize: 1,
                }
              },
          },
            interaction: {
              mode: 'nearest',
              axis: 'x',
            intersect: false,
            }
          },
        }
      } );
    }

    // Initialize Model Usage Chart (Pie Chart).
    const modelCanvas = document.getElementById( 'wyverncss-model-chart' );
    if ( modelCanvas ) {
      const modelCtx = modelCanvas.getContext( '2d' );

      new Chart( modelCtx, {
          type: 'pie',
          data: wyverncssData.modelChart,
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: true,
                position: 'right',
              },
              tooltip: {
                callbacks: {
                label: function ( context ) {
                  const label = context.label || '';
                  const value = context.parsed || 0;
                  const total = context.dataset.data.reduce( ( a, b ) => a + b, 0 );
                  const percentage = total > 0 ? ( ( value / total ) * 100 ).toFixed( 1 ) : 0;
                    return label + ': ' + value + ' (' + percentage + '%)';
                },
              },
            },
          },
        },
      } );
    }
  }

  // Initialize when DOM is ready.
  $( document ).ready( function () {
    // Only run on dashboard page.
    if ( $( '.wyverncss-dashboard' ).length > 0 ) {
        initDashboard();
      }
  } );
} )( jQuery );
