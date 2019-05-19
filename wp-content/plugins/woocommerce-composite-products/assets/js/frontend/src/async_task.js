/* @exclude */
/* jshint -W069 */
/* jshint -W041 */
/* jshint -W018 */
/* @endexclude */

/**
 * Implements a simple promise-like task runner.
 */
wc_cp_classes.WC_CP_Async_Task = function( task_callback, interval ) {

	var _task              = this,
		_done              = false,
		_waited            = 0,
		_complete_callback = function( result ) { return result; };

	interval      = interval || 100;
	task_callback = task_callback.bind( this );

	/**
	 * True if the task is done working.
	 */
	this.is_done = function() {
		return _done;
	};

	/**
	 * Return total time waiting.
	 */
	this.get_async_time = function() {
		return _waited;
	};

	/**
	 * Runs the task.
	 */
	this.run = function( result ) {

		setTimeout( function() {

			result = task_callback( result );

			if ( ! _task.is_done() ) {
				_waited += interval;
				_task.run( result );
			} else {
				_complete_callback( result );
			}
		}, interval );
	};

	/**
	 * Runs when the task is complete.
	 */
	this.done = function() {
		_done = true;
	};

	/**
	 * Runs when the task is complete.
	 */
	this.complete = function( done ) {
		_complete_callback = done;
	};

	this.run();
};
