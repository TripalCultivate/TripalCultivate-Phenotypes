<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\trpcultivate_phenotypes\Entity\PhenodataBackupInterface;

/**
 * Defines the phenotypic data backup entity type.
 *
 * @ConfigEntityType(
 *   id = "phenodata_backup",
 *   label = @Translation("Phenotypic Data Backup"),
 *   label_collection = @Translation("Phenotypic Data Backups"),
 *   label_singular = @Translation("phenotypic data backup"),
 *   label_plural = @Translation("phenotypic data backups"),
 *   label_count = @PluralTranslation(
 *     singular = "@count phenotypic data backup",
 *     plural = "@count phenotypic data backups",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\trpcultivate_phenotypes\ListBuilder\PhenodataBackupListBuilder",
 *     "form" = {
 *       "add" = "Drupal\trpcultivate_phenotypes\Form\PhenodataBackupForm",
 *       "edit" = "Drupal\trpcultivate_phenotypes\Form\PhenodataBackupForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "phenodata_backup",
 *   admin_permission = "administer phenodata_backup",
 *   links = {
 *     "collection" = "/admin/structure/phenodata-backup",
 *     "add-form" = "/admin/structure/phenodata-backup/add",
 *     "edit-form" = "/admin/structure/phenodata-backup/{phenodata_backup}",
 *     "delete-form" = "/admin/structure/phenodata-backup/{phenodata_backup}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "file_id" = "file_id",
 *     "project_id" = "project_id",
 *     "comments" = "comments",
 *     "backup_date" = "backup_date",
 *     "user_id" = "user_id",
 *   },
 *   config_export = {
 *     "id",
 *     "file_id",
 *     "project_id",
 *     "comments",
 *     "backup_date",
 *     "user_id",
 *   },
 * )
 */
final class PhenodataBackup extends ConfigEntityBase implements PhenodataBackupInterface {

  /**
   * The configuation entity ID number.
   */
  protected string $id;

  /**
   * The file object file id of the data file.
   */
  protected string $file_id;

  /**
   * The project id of the project the data file is specific to.
   */
  protected string $project_id;

  /**
   * User notes or comments about the data file.
   */
  protected string $comments;

  /**
   * The data the data file was uploaded.
   */
  protected string $backup_date;

  /**
   * The user id of the user who uploaded the data file.
   */
  protected string $user_id;

}
