import db from "../database/models/index.js";
const { ActivityLog } = db;

/**
 * Global activity logging service to record user activities
 */

/**
 * Create an activity log entry
 * @param {Object} logData - Activity log data
 * @param {number} logData.userId - User ID who performed the activity (optional, can be null for unauthenticated requests)
 * @param {string} logData.activity - Activity description
 * @param {string} logData.module - Module where activity occurred
 * @param {string} logData.action - Action type (CREATE, UPDATE, DELETE, LOGIN, LOGOUT, VIEW, ACTIVATE, DEACTIVATE)
 * @param {string} logData.details - Additional details (optional)
 * @param {number} logData.targetId - Target record ID (optional)
 * @param {string} logData.targetType - Target type (optional)
 * @param {string} logData.ipAddress - IP address (optional)
 * @param {string} logData.userAgent - User agent (optional)
 * @param {string} logData.status - Status (SUCCESS, FAILED, WARNING)
 * @param {string} logData.errorMessage - Error message (optional)
 */
export const logActivity = async (logData) => {
  try {
    await ActivityLog.create({
      userId: logData.userId || null, // Allow null for unauthenticated requests
      activity: logData.activity,
      module: logData.module,
      action: logData.action,
      details: logData.details || null,
      targetId: logData.targetId || null,
      targetType: logData.targetType || null,
      ipAddress: logData.ipAddress || null,
      userAgent: logData.userAgent || null,
      status: logData.status || 'SUCCESS',
      errorMessage: logData.errorMessage || null
    });

    console.log(`[ACTIVITY] ${logData.action} ${logData.activity} by User ${logData.userId || 'Anonymous'} in ${logData.module}`);
  } catch (error) {
    console.error("Error creating activity log:", error);
    // Don't throw error to avoid breaking the main API flow
  }
};

/**
 * Log user activity with request context
 * @param {Object} req - Express request object
 * @param {string} activity - Activity description
 * @param {string} module - Module name
 * @param {string} action - Action type
 * @param {string} details - Additional details (optional)
 * @param {number} targetId - Target record ID (optional)
 * @param {string} targetType - Target type (optional)
 * @param {string} status - Status (optional)
 * @param {string} errorMessage - Error message (optional)
 */
export const logUserActivity = (req, activity, module, action, details = null, targetId = null, targetType = null, status = 'SUCCESS', errorMessage = null) => {
  const userId = req.user ? req.user.id : null;
  const email = req.user ? req.user.email : (req.body?.email || req.params?.email || null);
  
  logActivity({
    userId: userId,
    activity: activity,
    module: module,
    action: action,
    details: details,
    targetId: targetId,
    targetType: targetType,
    ipAddress: req.ip || req.socket?.remoteAddress || req.connection?.remoteAddress,
    userAgent: req.headers?.['user-agent'] || req.get?.('User-Agent') || 'Unknown',
    status: status,
    errorMessage: errorMessage
  });
};


/**
 * Log successful activity
 * @param {Object} req - Express request object
 * @param {string} activity - Activity description
 * @param {string} module - Module name
 * @param {string} action - Action type
 * @param {string} details - Additional details (optional)
 * @param {number} targetId - Target record ID (optional)
 * @param {string} targetType - Target type (optional)
 */
export const logSuccess = (req, activity, module, action, details = null, targetId = null, targetType = null) => {
  logUserActivity(req, activity, module, action, details, targetId, targetType, 'SUCCESS');
};

/**
 * Log failed activity
 * @param {Object} req - Express request object
 * @param {string} activity - Activity description
 * @param {string} module - Module name
 * @param {string} action - Action type
 * @param {string} errorMessage - Error message
 * @param {string} details - Additional details (optional)
 * @param {number} targetId - Target record ID (optional)
 * @param {string} targetType - Target type (optional)
 */
export const logFailure = (req, activity, module, action, errorMessage, details = null, targetId = null, targetType = null) => {
  logUserActivity(req, activity, module, action, details, targetId, targetType, 'FAILED', errorMessage);
};

/**
 * Get activity logs with filtering options
 * @param {Object} filters - Filter options
 * @param {number} filters.userId - Filter by user ID
 * @param {string} filters.activity - Filter by activity (partial match)
 * @param {string} filters.module - Filter by module
 * @param {string} filters.action - Filter by action
 * @param {string} filters.status - Filter by status
 * @param {Date} filters.startDate - Filter by start date
 * @param {Date} filters.endDate - Filter by end date
 * @param {number} filters.limit - Limit results
 * @param {number} filters.offset - Offset results
 * @returns {Promise<Array>} Array of activity log entries
 */
export const getActivityLogs = async (filters = {}) => {
  try {
    const { Users } = db;
    
    const whereClause = {};
    
    // Apply filters
    if (filters.userId) {
      whereClause.userId = filters.userId;
    }
    
    if (filters.activity) {
      whereClause.activity = {
        [ActivityLog.sequelize.Op.like]: `%${filters.activity}%`
      };
    }
    
    if (filters.module) {
      whereClause.module = filters.module;
    }
    
    if (filters.action) {
      whereClause.action = filters.action;
    }
    
    if (filters.status) {
      whereClause.status = filters.status;
    }
    
    if (filters.startDate || filters.endDate) {
      whereClause.createdAt = {};
      if (filters.startDate) {
        whereClause.createdAt[ActivityLog.sequelize.Op.gte] = filters.startDate;
      }
      if (filters.endDate) {
        whereClause.createdAt[ActivityLog.sequelize.Op.lte] = filters.endDate;
      }
    }
    
    const logs = await ActivityLog.findAll({
      where: whereClause,
      include: [
        {
          model: Users,
          as: 'user',
          attributes: ['id', 'names', 'email', 'role'],
          required: false
        }
      ],
      order: [['createdAt', 'DESC']],
      limit: filters.limit || 100,
      offset: filters.offset || 0
    });

    return logs;
  } catch (error) {
    console.error("Error fetching activity logs:", error);
    throw error;
  }
};

/**
 * Get activity statistics
 * @returns {Promise<Object>} Activity statistics
 */
export const getActivityStatistics = async () => {
  try {
    const totalLogs = await ActivityLog.count();
    
    const logsByModule = await ActivityLog.findAll({
      attributes: ['module', [ActivityLog.sequelize.fn('COUNT', ActivityLog.sequelize.col('id')), 'count']],
      group: ['module']
    });
    
    const logsByAction = await ActivityLog.findAll({
      attributes: ['action', [ActivityLog.sequelize.fn('COUNT', ActivityLog.sequelize.col('id')), 'count']],
      group: ['action']
    });
    
    const logsByStatus = await ActivityLog.findAll({
      attributes: ['status', [ActivityLog.sequelize.fn('COUNT', ActivityLog.sequelize.col('id')), 'count']],
      group: ['status']
    });
    
    const successfulLogs = await ActivityLog.count({ where: { status: 'SUCCESS' } });
    const failedLogs = await ActivityLog.count({ where: { status: 'FAILED' } });
    const warningLogs = await ActivityLog.count({ where: { status: 'WARNING' } });

    return {
      totalLogs,
      successfulLogs,
      failedLogs,
      warningLogs,
      logsByModule: logsByModule.reduce((acc, log) => {
        acc[log.module] = parseInt(log.dataValues.count, 10);
        return acc;
      }, {}),
      logsByAction: logsByAction.reduce((acc, log) => {
        acc[log.action] = parseInt(log.dataValues.count, 10);
        return acc;
      }, {}),
      logsByStatus: logsByStatus.reduce((acc, log) => {
        acc[log.status] = parseInt(log.dataValues.count, 10);
        return acc;
      }, {})
    };
  } catch (error) {
    console.error("Error fetching activity statistics:", error);
    throw error;
  }
};

/**
 * Clean up old activity logs (keep logs for specified number of days)
 * @param {number} daysToKeep - Number of days to keep logs
 * @returns {Promise<number>} Number of deleted logs
 */
export const cleanupOldActivityLogs = async (daysToKeep = 90) => {
  try {
    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - daysToKeep);
    
    const deletedCount = await ActivityLog.destroy({
      where: {
        createdAt: {
          [ActivityLog.sequelize.Op.lt]: cutoffDate
        }
      }
    });

    console.log(`[ACTIVITY] Cleaned up ${deletedCount} old activity log entries`);
    return deletedCount;
  } catch (error) {
    console.error("Error cleaning up old activity logs:", error);
    throw error;
  }
};

/**
 * Express middleware to automatically log user activities
 * @param {string} module - Module name for the logged activities
 * @returns {Function} Express middleware function
 */
export const activityLoggingMiddleware = (module) => {
  return (req, res, next) => {
    // Store the original res.json to capture responses
    const originalJson = res.json;
    
    res.json = function(data) {
      // Log the activity based on the response
      if (req.user) {
        const action = req.method === 'GET' ? 'VIEW' : 
                    req.method === 'POST' ? 'CREATE' : 
                    req.method === 'PUT' || req.method === 'PATCH' ? 'UPDATE' : 
                    req.method === 'DELETE' ? 'DELETE' : 'UNKNOWN';
        
        const status = res.statusCode < 400 ? 'SUCCESS' : 'FAILED';
        const activity = `${action} ${req.originalUrl}`;
        const details = data && data.message ? data.message : null;
        const errorMessage = status === 'FAILED' && data && data.message ? data.message : null;
        
        logUserActivity(req, activity, module, action, details, null, null, status, errorMessage);
      }
      
      // Call original json
      return originalJson.call(this, data);
    };
    
    next();
  };
};
