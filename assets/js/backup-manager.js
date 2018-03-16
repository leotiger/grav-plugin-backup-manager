(function ($) {
  "use strict";
  $(window).on('load', function() {

	var backupcharts = {};
    $('#backup-maintenance .backups-chart').each(function() {
		var that = this;
		let name = $(this).data('chart-context') || '';
		let data = $(this).data('chart-data') || {};
		let container = $(this).find('.ct-chart-backup').empty()[0];
		if (name.length) {
			console.log(name);
			console.log(data);
			backupcharts[name] = new Chartist.Pie(container, data, {
				donut: true,
				donutWidth: 10,
				startAngle: 0,
				total: 100,
				showLabel: false,
				height: 150,
				chartPadding: 5
			});			
			backupcharts[name].on('created', function() {$(that).find('.hidden').removeClass('hidden')});
		}
	});
    // Missing optimizations and gulp but for 0.1.0 ok
	$('#force-backup').on('click', function() {
		let element = $(this);
		let url = element.data('backup');
		element.find('> .fa').removeClass('fa-suitcase').addClass('fa-spin fa-spinner');
		
		$('#admin-dashboard').find('a, button').each(function() {
			$(this).attr('disabled','disabled');
		});
		
		$("#download-backup").addClass('hidden');
		if (!$('#purgeStats').hasClass('hidden')) {
			$('#purgeStats').addClass('hidden');
		}
		if ($('#backupStats').hasClass('hidden')) {
			$('#backupStats').removeClass('hidden');
		}
		clearBackupResults();
		var request = $.ajax({
		  url: url,
		  method: "GET",
		  dataType: "json",
		});
		request.done(function( data ) {
			if (data.status == 'success') { 
				processBackupResults(data);
			}
			else {
				toastr.warning(data.message, "", {closeButton: false, timeOut:"0", extendedTimeOut: "0","newestOnTop": true});							
			}
		});
		request.fail(function(jqXHR, textStatus, errorMsg) {
			toastr.error(errorMsg, 'Error!', {timeOut:8000});
		});
		request.always(function() {
			element
				.addClass('hidden')
				.removeAttr('disabled')
				.find('> .fa').removeClass('fa-spin fa-spinner').addClass('fa-suitcase');		  
			$('#admin-dashboard').find('a, button').each(function() {
				$(this).removeAttr('disabled');
			});
		});
	});

	$('[data-backup*="backuptask"]').on('click', function() {
		let element = $(this);
		let url = element.data('backup');
		element
			.closest('.button-group')
			.find('.fa-suitcase').removeClass('fa-suitcase').addClass('fa-spin fa-spinner');
		
		$('#admin-dashboard').find('a, button').each(function() {
			$(this).attr('disabled', 'disabled');
		});
		
		$("#download-backup").addClass('hidden');		
		
		if (!$('#purgeStats').hasClass('hidden')) {
			$('#purgeStats').addClass('hidden');
		}
		if ($('#backupStats').hasClass('hidden')) {
			$('#backupStats').removeClass('hidden');
		}
		
		if (!$("#force-backup").hasClass('hidden')) {
			$("#force-backup").addClass('hidden');
		}
		if (!$("#force-purge").hasClass('hidden')) {
			$("#force-purge").addClass('hidden');
		}
		
		clearBackupResults();
		
		var request = $.ajax({
		  url: url,
		  method: "GET",
		  dataType: "json",
		});
		request.done(function( data ) {
			if (data.status == 'success') { 
				processBackupResults(data);
			}
			else {
				toastr.warning(data.message, "", {closeButton: false, timeOut:"0", extendedTimeOut: "0","newestOnTop": true});							
			}
		});
		request.fail(function(jqXHR, textStatus, errorMsg) {
			toastr.error(errorMsg, 'Error!', {timeOut:8000});			
		});
		request.always(function() {
			element
				.removeAttr('disabled')
				.closest('.button-group')
				.find('.fa-spin').removeClass('fa-spin fa-spinner').addClass('fa-suitcase');		  
			$('#admin-dashboard').find('a, button').each(function() {
				$(this).removeAttr('disabled');
			});
		});
	});
	$('[data-purge*="backuptask"]').on('click', function() {
		let element = $(this);
		let url = element.data('purge');
		element
			.attr('disabled', 'disabled')
			.closest('.button-group')
			.find('i[class*="fa-batt"]').addClass('fa-spin');
		
		$('#admin-dashboard').find('a, button').each(function() {
			$(this).attr('disabled', 'disabled');
		});

		if (!$("#force-backup").hasClass('hidden')) {
			$("#force-backup").addClass('hidden');
		}
		if (!$("#force-purge").hasClass('hidden')) {
			$("#force-purge").addClass('hidden');
		}
		
		if ($('#purgeStats').hasClass('hidden')) {
			$('#purgeStats').removeClass('hidden');
		}
		if (!$('#backupStats').hasClass('hidden')) {
			$('#backupStats').addClass('hidden');
		}
		clearPurgeResults();
		
		var purge = $.ajax({
		  url: url,
		  method: "GET",
		  dataType: "json",
		});
		purge.done(function( data ) {
			if (data.status == 'success') { 
				processPurgeResults(data);			
			}
			else {
				toastr.warning(data.message, "", {closeButton: false, timeOut:"0", extendedTimeOut: "0","newestOnTop": true});							
			}
		});
		purge.fail(function(jqXHR, textStatus, errorMsg) {
			toastr.error(errorMsg, 'Error!', {timeout:8000});				
		});
		purge.always(function() {
			element
				.removeAttr('disabled')
				.closest('.button-group')
				.find('.fa-spin').removeClass('fa-spin');			
			$('#admin-dashboard').find('a, button').each(function() {
				$(this).removeAttr('disabled');
			});
		});
	});

	$('#force-purge').on('click', function() {
		let element = $(this);
		let url = element.data('purge');
		element.find('i[class*="fa-batt"]').addClass('fa-spin');
		
		$('#admin-dashboard').find('a, button').each(function() {
			$(this).attr('disabled','disabled');
		});
		
		$("#download-backup").addClass('hidden');
		if ($('#purgeStats').hasClass('hidden')) {
			$('#purgeStats').removeClass('hidden');
		}
		if (!$('#backupStats').hasClass('hidden')) {
			$('#backupStats').addClass('hidden');
		}
		clearBackupResults();
		var request = $.ajax({
		  url: url,
		  method: "GET",
		  dataType: "json",
		});
		request.done(function( data ) {
			if (data.status == 'success') { 
				processPurgeResults(data);
			}
			else {
				toastr.warning(data.message, "", {closeButton: false, timeOut:"0", extendedTimeOut: "0","newestOnTop": true});							
			}
			$('#admin-dashboard').find('a, button').each(function() {
				$(this).removeAttr('disabled');
			});
		});
		request.fail(function(jqXHR, textStatus, errorMsg) {
			toastr.error(errorMsg, 'Error!', {timeout:8000});
			$('#admin-dashboard').find('a, button').each(function() {
				$(this).removeAttr('disabled');
			});
		});
		request.always(function() {
			element
				.addClass('hidden')
				.find('.fa-spin').removeClass('fa-spin');
		});
	});

	var processBackupResults = function(data) {
		let last = data.last;
		let testmode = last.runInTestMode ? " (Test Mode)" : "";
		if (testmode == "") {
			$('#store-used').html(data.storestatus.used);
			backupcharts['storage'].update({"series":[data.storestatus.chart_fill, data.storestatus.chart_empty]});
			backupcharts['lastbackup'].update({"series":[data.lastbackup.chart_fill, data.lastbackup.chart_empty]});				
			$('#backupdaysindicator').html(data.lastbackup.days);
			$('#backupdayslabel').html(data.lastbackup.dayslabel);	
		}
		toastr.success(data.message, data.backuptype + testmode, {closeButton:data.toastr.closeButton, timeOut:data.toastr.timeOut, extendedTimeOut: data.toastr.extendedTimeOut, "newestOnTop": true, "preventDuplicates": true});

		$('#process-timeout').html(data.last.processTimeout + 's');
		$('#duration').html(data.last.backupDuration);
		$('#zip-status').html(data.last.zipFileStatus);
		$('#excluded-files').html(data.last.filesExcluded);
		$('#bytes-to-zip').html(data.last.bytesToZip);
		$('#bytes-zipped').html(data.last.zippedBytes);
		$('#savings').html(data.last.zipSavings + '%');
		$('#ratio').html(data.last.compressionRatio);
		$('#backup-type').html(data.backuptype);

		$('#store-partials').html(data.filestats.partials);
		$('#store-period').html(data.filestats.period);
		$('#store-instance').html(data.filestats.instance);
		$('#store-failed').html(data.filestats.failed);
		$('#store-tests').html(data.filestats.tests);
		
		$("#download-backup").html(data.downbtnlabel).attr('href', data.urlzip).removeClass('hidden');
		if (data.forcebackup.length) {
			$("#force-backup")
				.data('backup', data.forcebackup)
				.removeClass('hidden')
				.find('span').html(data.backuptype);
		}
	}
	
	var clearBackupResults = function(data) {
		$('#process-timeout').empty();
		$('#duration').empty();
		$('#zip-status').empty();
		$('#excluded-files').empty();
		$('#bytes-to-zip').empty();
		$('#bytes-zipped').empty();
		$('#savings').empty();
		$('#ratio').empty();
		$('#backup-type').empty();
		if (!$("#force-purge").hasClass('hidden')) {
			$("#force-purge").addClass('hidden');
		}		
	}

	var processPurgeResults = function(data) {
		let last = data.last;
		let testmode = last.runInTestMode ? " (Test Mode)" : "";
		if (testmode == "") {
			$('#store-used').html(data.storestatus.used);
			backupcharts['storage'].update({"series":[data.storestatus.chart_fill, data.storestatus.chart_empty]});		
			backupcharts['lastbackup'].update({"series":[data.lastbackup.chart_fill, data.lastbackup.chart_empty]});				
			$('#backupdaysindicator').html(data.lastbackup.days);
			$('#backupdayslabel').html(data.lastbackup.dayslabel);	
		}
		toastr.success(data.message, data.backuptype + testmode, {closeButton:data.toastr.closeButton, timeOut:data.toastr.timeOut, extendedTimeOut: data.toastr.extendedTimeOut, "newestOnTop": true, "preventDuplicates": true});

		$('#purge-type').html(data.backuptype);
		$('#store-location').html(data.last.store);
		$('#store-capacity').html(data.storestatus.capacity);
		$('#storage-used').html(data.storestatus.used);
		$('#keep-days').html(data.last.keepDays);
		$('#files-deleted').html(data.last.filesDeleted);
		$('#purged-bytes').html(data.last.purgedTranslated);
		$('#files-over-days').html(data.last.purgedFilesExceededMaxDay);
		$('#files-over-capacity').html(data.last.purgedFilesExceededMaxCapacity);

		$('#store-partials').html(data.filestats.partials);
		$('#store-period').html(data.filestats.period);
		$('#store-instance').html(data.filestats.instance);
		$('#store-failed').html(data.filestats.failed);
		$('#store-tests').html(data.filestats.tests);
		
		if (data.forcepurge.length) {
			$("#force-purge")
				.data('purge', data.forcepurge)
				.removeClass('hidden')
				.find('span').html(data.backuptype);
		}
	}
	var clearPurgeResults = function(data) {
		$('#purge-type').empty();
		$('#store-location').empty();
		$('#store-capacity').empty();
		$('#storage-used').empty();
		$('#keep-days').empty();
		$('#files-deleted').empty();
		$('#purged-bytes').empty();
		$('#files-over-days').empty();
		$('#files-over-capacity').empty();
		if (!$("#force-backup").hasClass('hidden')) {
			$("#force-backup").addClass('hidden');
		}
	}
  });
  
})(jQuery);
